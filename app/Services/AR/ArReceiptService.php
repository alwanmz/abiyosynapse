<?php

namespace App\Services\AR;

use App\Models\ArReceipt;
use App\Models\BankAccount;
use App\Models\SalesInvoice;
use App\Models\User;
use App\Services\Accounting\JournalPostingService;
use App\Services\CurrentCompany;
use App\Services\CurrencyDocumentService;
use App\Services\MoneyConversionService;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use App\Services\Accounting\AccountRoleResolver;
use App\Support\AccountRole;

/**
 * Records an AR Receipt: money actually collected from a customer,
 * applied against one or more of their posted Sales Invoices (blueprint
 * event mapping: AR Receipt -> Bank/Cash debit, AR credit). Reuses Fase
 * 6's BankAccount for the debit side rather than a hardcoded account
 * code, since the money always lands in a specific bank/cash account the
 * user picks — mirrors how CashTransactionService resolves its debit
 * side, but the credit side here is always 1.1.3 Piutang Usaha rather
 * than a user-chosen counter account, since AR receipts are a fixed
 * business event, not a generic manual entry.
 *
 * Each line's amount_applied is capped at that invoice's current
 * outstandingAmount() (total - paid_amount - returns), and the receipt's
 * total amount must equal the sum of its lines exactly — no unapplied
 * "on account" remainder in this iteration, matching how Fase 4/5 keep
 * every allocation fully explicit rather than introducing an
 * unapplied-cash concept.
 */
class ArReceiptService
{
    private const AR_ACCOUNT_ROLE = AccountRole::AccountsReceivable;
    private const FX_GAIN_ACCOUNT_ROLE = AccountRole::FxGain;
    private const FX_LOSS_ACCOUNT_ROLE = AccountRole::FxRealizedLoss;
    private const AMOUNT_TOLERANCE = 0.01;

    public function __construct(
        private readonly CurrentCompany $currentCompany,
        private readonly JournalPostingService $posting,
        private readonly CurrencyDocumentService $currencyDocuments,
        private readonly MoneyConversionService $money,
    ) {
    }

    public function create(array $input, array $lineInputs, ?User $creator = null): ArReceipt
    {
        return DB::transaction(function () use ($input, $lineInputs, $creator) {
            /** @var BankAccount $bankAccount */
            $bankAccount = BankAccount::findOrFail($input['bank_account_id']);

            $totalApplied = 0.0;
            $totalAppliedBase = 0.0;
            $validatedLines = [];

            foreach ($lineInputs as $lineInput) {
                /** @var SalesInvoice $invoice */
                $invoice = SalesInvoice::where('customer_id', $input['customer_id'])
                    ->where('id', $lineInput['sales_invoice_id'])
                    ->firstOrFail();

                $amountApplied = (float) $lineInput['amount_applied'];

                if ($amountApplied <= 0) {
                    throw new RuntimeException('Amount applied to an invoice must be greater than zero.');
                }

                if ($amountApplied > $invoice->outstandingAmount() + self::AMOUNT_TOLERANCE) {
                    throw new RuntimeException("Amount applied to invoice {$invoice->number} exceeds its outstanding balance.");
                }

                $invoiceBase = $this->currencyDocuments->baseAmount((string) $amountApplied, [
                    'currency_code' => $invoice->currency_code,
                    'exchange_rate' => $invoice->exchange_rate,
                    'effective_date' => $invoice->invoice_date?->toDateString() ?? now()->toDateString(),
                ]);
                $validatedLines[] = ['invoice' => $invoice, 'amount' => $amountApplied, 'amount_base' => (float) $invoiceBase];
                $totalApplied += $amountApplied;
                $totalAppliedBase += (float) $invoiceBase;
            }

            $receiptAmount = (float) $input['amount'];
            $paymentConversion = $this->money->convert(
                (string) $receiptAmount,
                $bankAccount->currency_code,
                $this->money->baseCurrency(),
                $input['receipt_date'],
            );
            $receiptAmountBase = (float) $paymentConversion['amount'];

            $invoiceCurrencies = collect($validatedLines)->map(fn (array $line) => $line['invoice']->currency_code)->unique();
            if ($invoiceCurrencies->count() === 1 && $invoiceCurrencies->first() === $bankAccount->currency_code && abs($receiptAmount - $totalApplied) > self::AMOUNT_TOLERANCE) {
                throw new RuntimeException('Receipt amount must equal the sum of amounts applied to invoices.');
            }

            $receipt = ArReceipt::create([
                'number' => $this->nextNumber(),
                'customer_id' => $input['customer_id'],
                'bank_account_id' => $bankAccount->id,
                'receipt_date' => $input['receipt_date'],
                'currency_code' => $bankAccount->currency_code,
                'exchange_rate' => $paymentConversion['rate'],
                'amount' => $receiptAmount,
                'amount_base' => $receiptAmountBase,
                'reference' => $input['reference'] ?? null,
                'created_by' => $creator?->id,
            ]);

            foreach ($validatedLines as $line) {
                $receipt->lines()->create([
                    'sales_invoice_id' => $line['invoice']->id,
                    'amount_applied' => $line['amount'],
                    'amount_applied_base' => $line['amount_base'],
                ]);

                $line['invoice']->increment('paid_amount', $line['amount']);
                $line['invoice']->increment('paid_amount_base', $line['amount_base']);
            }

            $difference = round($receiptAmountBase - $totalAppliedBase, 6);
            $lines = [
                ['account_id' => $bankAccount->account_id, 'debit' => $receiptAmountBase, 'amount_currency' => $receiptAmount, 'currency_code' => $bankAccount->currency_code, 'exchange_rate' => $paymentConversion['rate']],
            ];
            foreach ($validatedLines as $line) {
                $lines[] = ['account_id' => $this->accountId(self::AR_ACCOUNT_ROLE), 'credit' => $line['amount_base'], 'amount_currency' => -$line['amount'], 'currency_code' => $line['invoice']->currency_code, 'exchange_rate' => $line['invoice']->exchange_rate];
            }
            if (abs($difference) > self::AMOUNT_TOLERANCE) {
                $lines[] = $difference > 0
                    ? ['account_id' => $this->accountId(self::FX_GAIN_ACCOUNT_ROLE), 'credit' => $difference, 'amount_currency' => -$difference, 'currency_code' => $this->money->baseCurrency()]
                    : ['account_id' => $this->accountId(self::FX_LOSS_ACCOUNT_ROLE), 'debit' => abs($difference), 'amount_currency' => abs($difference), 'currency_code' => $this->money->baseCurrency()];
            }

            $this->posting->post(
                description: "AR receipt {$receipt->number}",
                lines: $lines,
                sourceable: $receipt,
                entryDate: $input['receipt_date'],
            );

            return $receipt->fresh('lines');
        });
    }

    private function nextNumber(): string
    {
        $prefix = 'AR-' . now()->format('Y') . '-';

        $lastNumber = ArReceipt::where('number', 'like', $prefix . '%')
            ->orderByRaw('CAST(SUBSTR(number, ' . (strlen($prefix) + 1) . ') AS INTEGER) DESC')
            ->value('number');

        $nextSequence = $lastNumber ? ((int) substr($lastNumber, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad((string) $nextSequence, 6, '0', STR_PAD_LEFT);
    }

    private function accountId(AccountRole $role): int
    {
        return app(AccountRoleResolver::class)->id($role);
    }
}
