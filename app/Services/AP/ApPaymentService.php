<?php

namespace App\Services\AP;

use App\Models\Account;
use App\Models\ApPayment;
use App\Models\BankAccount;
use App\Models\SupplierInvoice;
use App\Models\User;
use App\Services\Accounting\JournalPostingService;
use App\Services\CurrentCompany;
use App\Services\CurrencyDocumentService;
use App\Services\MoneyConversionService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Records an AP Payment: money actually paid to a supplier, applied
 * against one or more of their matched Supplier Invoices (blueprint
 * event mapping: AP Payment -> AP debit, Bank/Cash credit — the mirror
 * image of ArReceiptService's Bank debit / AR credit). Reuses Fase 6's
 * BankAccount for the credit side rather than a hardcoded account code.
 *
 * Only a `matched` invoice can be paid — `pending_match` and `disputed`
 * invoices never had AP posted against them by SupplierInvoiceService's
 * 3-way match (see SupplierInvoice::isPayable()), so paying one would
 * create a payment against a liability that doesn't actually exist yet.
 * Each line's amount_applied is capped at that invoice's current
 * outstandingAmount() (total - paid_amount; no Purchase Return concept
 * exists yet to subtract, unlike the AR side's Sales Return), and the
 * payment's total amount must equal the sum of its lines exactly.
 */
class ApPaymentService
{
    private const AP_ACCOUNT_CODE = '2.1.1';
    private const FX_GAIN_ACCOUNT_CODE = '4.2';
    private const FX_LOSS_ACCOUNT_CODE = '5.6';
    private const AMOUNT_TOLERANCE = 0.01;

    public function __construct(
        private readonly CurrentCompany $currentCompany,
        private readonly JournalPostingService $posting,
        private readonly CurrencyDocumentService $currencyDocuments,
        private readonly MoneyConversionService $money,
    ) {
    }

    public function create(array $input, array $lineInputs, ?User $creator = null): ApPayment
    {
        return DB::transaction(function () use ($input, $lineInputs, $creator) {
            /** @var BankAccount $bankAccount */
            $bankAccount = BankAccount::findOrFail($input['bank_account_id']);

            $totalApplied = 0.0;
            $totalAppliedBase = 0.0;
            $validatedLines = [];

            foreach ($lineInputs as $lineInput) {
                /** @var SupplierInvoice $invoice */
                $invoice = SupplierInvoice::where('supplier_id', $input['supplier_id'])
                    ->where('id', $lineInput['supplier_invoice_id'])
                    ->firstOrFail();

                if (! $invoice->isMatched()) {
                    throw new RuntimeException("Invoice {$invoice->number} has not been matched yet and has no payable AP balance.");
                }

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

            $paymentAmount = (float) $input['amount'];
            $paymentConversion = $this->money->convert(
                (string) $paymentAmount,
                $bankAccount->currency_code,
                $this->money->baseCurrency(),
                $input['payment_date'],
            );
            $paymentAmountBase = (float) $paymentConversion['amount'];

            $invoiceCurrencies = collect($validatedLines)->map(fn (array $line) => $line['invoice']->currency_code)->unique();
            if ($invoiceCurrencies->count() === 1 && $invoiceCurrencies->first() === $bankAccount->currency_code && abs($paymentAmount - $totalApplied) > self::AMOUNT_TOLERANCE) {
                throw new RuntimeException('Payment amount must equal the sum of amounts applied to invoices.');
            }

            $payment = ApPayment::create([
                'number' => $this->nextNumber(),
                'supplier_id' => $input['supplier_id'],
                'bank_account_id' => $bankAccount->id,
                'payment_date' => $input['payment_date'],
                'currency_code' => $bankAccount->currency_code,
                'exchange_rate' => $paymentConversion['rate'],
                'amount' => $paymentAmount,
                'amount_base' => $paymentAmountBase,
                'reference' => $input['reference'] ?? null,
                'created_by' => $creator?->id,
            ]);

            foreach ($validatedLines as $line) {
                $payment->lines()->create([
                    'supplier_invoice_id' => $line['invoice']->id,
                    'amount_applied' => $line['amount'],
                    'amount_applied_base' => $line['amount_base'],
                ]);

                $line['invoice']->increment('paid_amount', $line['amount']);
                $line['invoice']->increment('paid_amount_base', $line['amount_base']);
            }

            $difference = round($paymentAmountBase - $totalAppliedBase, 6);
            $lines = [];
            foreach ($validatedLines as $line) {
                $lines[] = ['account_id' => $this->accountId(self::AP_ACCOUNT_CODE), 'debit' => $line['amount_base'], 'amount_currency' => $line['amount'], 'currency_code' => $line['invoice']->currency_code, 'exchange_rate' => $line['invoice']->exchange_rate];
            }
            if (abs($difference) > self::AMOUNT_TOLERANCE) {
                $lines[] = $difference > 0
                    ? ['account_id' => $this->accountId(self::FX_LOSS_ACCOUNT_CODE), 'debit' => $difference, 'amount_currency' => $difference, 'currency_code' => $this->money->baseCurrency()]
                    : ['account_id' => $this->accountId(self::FX_GAIN_ACCOUNT_CODE), 'credit' => abs($difference), 'amount_currency' => -abs($difference), 'currency_code' => $this->money->baseCurrency()];
            }
            $lines[] = ['account_id' => $bankAccount->account_id, 'credit' => $paymentAmountBase, 'amount_currency' => -$paymentAmount, 'currency_code' => $bankAccount->currency_code, 'exchange_rate' => $paymentConversion['rate']];

            $this->posting->post(
                description: "AP payment {$payment->number}",
                lines: $lines,
                sourceable: $payment,
                entryDate: $input['payment_date'],
            );

            return $payment->fresh('lines');
        });
    }

    private function nextNumber(): string
    {
        $prefix = 'AP-' . now()->format('Y') . '-';

        $lastNumber = ApPayment::where('number', 'like', $prefix . '%')
            ->orderByRaw('CAST(SUBSTR(number, ' . (strlen($prefix) + 1) . ') AS INTEGER) DESC')
            ->value('number');

        $nextSequence = $lastNumber ? ((int) substr($lastNumber, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad((string) $nextSequence, 6, '0', STR_PAD_LEFT);
    }

    private function accountId(string $code): int
    {
        $companyId = $this->currentCompany->id();
        $account = Account::where('company_id', $companyId)->where('code', $code)->first();

        if (! $account) {
            throw new RuntimeException("Chart of accounts is missing the expected account \"{$code}\" for AP postings.");
        }

        return $account->id;
    }
}
