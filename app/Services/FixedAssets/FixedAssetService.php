<?php

namespace App\Services\FixedAssets;

use App\Models\Account;
use App\Models\FixedAsset;
use App\Models\FixedAssetDepreciation;
use App\Models\User;
use App\Services\Accounting\JournalPostingService;
use App\Services\CurrentCompany;
use App\Services\CurrencyDocumentService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use App\Services\Accounting\AccountRoleResolver;
use App\Support\AccountRole;

/**
 * Fixed asset lifecycle and accounting integration.
 *
 * Draft registration does not post anything. Activation capitalizes the
 * acquisition cost, each depreciation period posts exactly once, and
 * disposal removes both the asset cost and its accumulated depreciation
 * while recognizing proceeds and gain/loss.
 */
class FixedAssetService
{
    public function __construct(
        private readonly CurrentCompany $currentCompany,
        private readonly JournalPostingService $posting,
        private readonly CurrencyDocumentService $currencyDocuments,
    ) {
    }

    public function create(array $input, ?User $creator = null): FixedAsset
    {
        $companyId = $this->requireCompanyId();
        $this->validateEconomics($input);
        $currencyContext = $this->currencyDocuments->resolve($input['currency_code'] ?? null, $input['acquisition_date']);

        return FixedAsset::create([
            'company_id' => $companyId,
            'number' => $this->nextNumber($companyId),
            'name' => $input['name'],
            'category' => $input['category'] ?? null,
            'asset_account_id' => $input['asset_account_id'],
            'accumulated_depreciation_account_id' => $input['accumulated_depreciation_account_id'],
            'depreciation_expense_account_id' => $input['depreciation_expense_account_id'],
            'source_account_id' => $input['source_account_id'],
            'acquisition_date' => $input['acquisition_date'],
            'currency_code' => $currencyContext['currency_code'],
            'exchange_rate' => $currencyContext['exchange_rate'],
            'placed_in_service_date' => $input['placed_in_service_date'] ?? null,
            'acquisition_cost' => $input['acquisition_cost'],
            'acquisition_cost_base' => $this->currencyDocuments->baseAmount($input['acquisition_cost'], $currencyContext),
            'salvage_value' => $input['salvage_value'] ?? 0,
            'salvage_value_base' => $this->currencyDocuments->baseAmount($input['salvage_value'] ?? 0, $currencyContext),
            'useful_life_months' => $input['useful_life_months'],
            'depreciation_method' => $input['depreciation_method'] ?? 'straight_line',
            'status' => 'draft',
            'notes' => $input['notes'] ?? null,
            'created_by' => $creator?->id,
        ]);
    }

    public function activate(FixedAsset $asset, ?User $actor = null): FixedAsset
    {
        return DB::transaction(function () use ($asset, $actor) {
            $asset = $this->lockAsset($asset);

            if (! $asset->isDraft()) {
                throw new RuntimeException("Fixed asset {$asset->number} is not in draft status.");
            }

            $this->validateEconomics($asset->toArray());
            $this->assertAccountsBelongToCompany($asset, [
                $asset->asset_account_id,
                $asset->source_account_id,
            ]);

            $placedInServiceDate = $asset->placed_in_service_date?->toDateString()
                ?? $asset->acquisition_date->toDateString();

            $this->posting->post(
                description: "Capitalization {$asset->number}: {$asset->name}",
                lines: [
                    ['account_id' => $asset->asset_account_id, 'debit_base' => (string) $asset->acquisition_cost_base, 'currency_code' => $asset->currency_code, 'exchange_rate' => $asset->exchange_rate],
                    ['account_id' => $asset->source_account_id, 'credit_base' => (string) $asset->acquisition_cost_base, 'currency_code' => $asset->currency_code, 'exchange_rate' => $asset->exchange_rate],
                ],
                sourceable: $asset,
                entryDate: $placedInServiceDate,
            );

            $asset->update([
                'status' => 'active',
                'placed_in_service_date' => $placedInServiceDate,
                'activated_at' => now(),
                'activated_by' => $actor?->id,
            ]);

            return $asset->fresh(['assetAccount', 'accumulatedDepreciationAccount', 'depreciationExpenseAccount', 'sourceAccount', 'depreciations']);
        });
    }

    public function depreciate(FixedAsset $asset, string $periodDate, ?User $actor = null): FixedAssetDepreciation
    {
        $period = Carbon::parse($periodDate)->endOfMonth();

        if ($period->copy()->startOfMonth()->isFuture()) {
            throw new RuntimeException('Depreciation cannot be posted for a future period.');
        }

        return DB::transaction(function () use ($asset, $period, $actor) {
            $asset = $this->lockAsset($asset);

            if (! $asset->isDepreciable()) {
                throw new RuntimeException("Fixed asset {$asset->number} is not available for depreciation.");
            }

            $placedInService = $asset->placed_in_service_date;
            if (! $placedInService || $period->copy()->endOfMonth()->lt($placedInService->copy()->endOfMonth())) {
                throw new RuntimeException('Depreciation period cannot be before the placed-in-service month.');
            }

            if ($asset->depreciations()->whereDate('period_date', $period->toDateString())->exists()) {
                throw new RuntimeException("Depreciation for {$period->format('F Y')} has already been posted for {$asset->number}.");
            }

            $remaining = $asset->depreciableBase() - (float) $asset->accumulated_depreciation;
            $monthlyAmount = round($asset->depreciableBase() / $asset->useful_life_months, 2);
            $amount = min($monthlyAmount, round($remaining, 2));

            if ($amount <= 0) {
                throw new RuntimeException("Fixed asset {$asset->number} has no depreciable balance remaining.");
            }

            $newAccumulated = round((float) $asset->accumulated_depreciation + $amount, 2);
            $amountBase = $this->currencyDocuments->baseAmount($amount, [
                'currency_code' => $asset->currency_code,
                'exchange_rate' => (string) $asset->exchange_rate,
                'effective_date' => $asset->acquisition_date->toDateString(),
            ]);
            $newAccumulatedBase = round((float) $asset->accumulated_depreciation_base + (float) $amountBase, 6);
            $this->assertAccountsBelongToCompany($asset, [
                $asset->accumulated_depreciation_account_id,
                $asset->depreciation_expense_account_id,
            ]);

            $depreciation = FixedAssetDepreciation::create([
                'company_id' => $asset->company_id,
                'fixed_asset_id' => $asset->id,
                'period_date' => $period->toDateString(),
                'amount' => $amount,
                'amount_base' => $amountBase,
                'accumulated_depreciation' => $newAccumulated,
                'accumulated_depreciation_base' => $newAccumulatedBase,
                'posted_by' => $actor?->id,
            ]);

            $journal = $this->posting->post(
                description: "Depreciation {$asset->number}: {$period->format('F Y')}",
                lines: [
                    ['account_id' => $asset->depreciation_expense_account_id, 'debit_base' => $amountBase, 'currency_code' => $asset->currency_code, 'exchange_rate' => $asset->exchange_rate],
                    ['account_id' => $asset->accumulated_depreciation_account_id, 'credit_base' => $amountBase, 'currency_code' => $asset->currency_code, 'exchange_rate' => $asset->exchange_rate],
                ],
                sourceable: $depreciation,
                entryDate: $period->toDateString(),
            );

            $depreciation->update(['journal_entry_id' => $journal->id]);
            $asset->update([
                'accumulated_depreciation' => $newAccumulated,
                'accumulated_depreciation_base' => $newAccumulatedBase,
                'status' => $newAccumulated >= $asset->depreciableBase() ? 'fully_depreciated' : 'active',
            ]);

            return $depreciation->fresh(['journalEntry', 'fixedAsset']);
        });
    }

    public function dispose(
        FixedAsset $asset,
        float $proceeds,
        ?int $proceedsAccountId,
        string $disposalDate,
        ?User $actor = null,
    ): FixedAsset {
        if ($proceeds < 0) {
            throw new RuntimeException('Disposal proceeds cannot be negative.');
        }

        return DB::transaction(function () use ($asset, $proceeds, $proceedsAccountId, $disposalDate, $actor) {
            $asset = $this->lockAsset($asset);

            if (! in_array($asset->status, ['active', 'fully_depreciated'], true)) {
                throw new RuntimeException("Fixed asset {$asset->number} cannot be disposed from its current status.");
            }

            if ($proceeds > 0 && ! $proceedsAccountId) {
                throw new RuntimeException('A proceeds account is required when disposal proceeds are greater than zero.');
            }

            $this->assertAccountsBelongToCompany($asset, [
                $asset->asset_account_id,
                $asset->accumulated_depreciation_account_id,
                $asset->depreciation_expense_account_id,
            ]);

            if ($proceedsAccountId) {
                $this->assertAccountBelongsToCompany($proceedsAccountId, $asset->company_id);
            }

            $bookValue = $asset->bookValue();
            $proceedsBase = (float) $this->currencyDocuments->baseAmount($proceeds, [
                'currency_code' => $asset->currency_code,
                'exchange_rate' => (string) $asset->exchange_rate,
                'effective_date' => $asset->acquisition_date->toDateString(),
            ]);
            $bookValueBase = (float) $asset->acquisition_cost_base - (float) $asset->accumulated_depreciation_base;
            $gainLoss = round($proceeds - $bookValue, 2);
            $gainLossBase = round($proceedsBase - $bookValueBase, 6);
            $lines = [];

            if ((float) $asset->accumulated_depreciation > 0) {
                $lines[] = [
                    'account_id' => $asset->accumulated_depreciation_account_id,
                    'debit_base' => (string) $asset->accumulated_depreciation_base,
                    'currency_code' => $asset->currency_code,
                    'exchange_rate' => $asset->exchange_rate,
                ];
            }

            if ($proceeds > 0) {
                $lines[] = ['account_id' => $proceedsAccountId, 'debit_base' => $proceedsBase, 'currency_code' => $asset->currency_code, 'exchange_rate' => $asset->exchange_rate];
            }

            if ($gainLoss < 0) {
                $lines[] = [
                    'account_id' => $asset->depreciation_expense_account_id,
                    'debit_base' => abs($gainLossBase),
                    'currency_code' => $asset->currency_code,
                    'exchange_rate' => $asset->exchange_rate,
                ];
            }

            $lines[] = ['account_id' => $asset->asset_account_id, 'credit_base' => (string) $asset->acquisition_cost_base, 'currency_code' => $asset->currency_code, 'exchange_rate' => $asset->exchange_rate];

            if ($gainLoss > 0) {
                $gainAccount = app(AccountRoleResolver::class)->account(AccountRole::AssetDisposalGain, $asset->company_id);

                $lines[] = ['account_id' => $gainAccount->id, 'credit_base' => $gainLossBase, 'currency_code' => $asset->currency_code, 'exchange_rate' => $asset->exchange_rate];
            }

            $this->posting->post(
                description: "Disposal {$asset->number}: {$asset->name}",
                lines: $lines,
                sourceable: $asset,
                entryDate: $disposalDate,
            );

            $asset->update([
                'status' => 'disposed',
                'disposed_at' => Carbon::parse($disposalDate),
                'disposed_by' => $actor?->id,
                'disposal_proceeds' => $proceeds,
                'disposal_proceeds_base' => $proceedsBase,
                'disposal_gain_loss' => $gainLoss,
                'disposal_gain_loss_base' => $gainLossBase,
            ]);

            return $asset->fresh(['assetAccount', 'accumulatedDepreciationAccount', 'depreciationExpenseAccount', 'depreciations']);
        });
    }

    private function validateEconomics(array $input): void
    {
        if ((float) ($input['acquisition_cost'] ?? 0) <= 0) {
            throw new RuntimeException('Acquisition cost must be greater than zero.');
        }

        if ((float) ($input['salvage_value'] ?? 0) < 0 || (float) ($input['salvage_value'] ?? 0) > (float) $input['acquisition_cost']) {
            throw new RuntimeException('Salvage value must be between zero and acquisition cost.');
        }

        if ((int) ($input['useful_life_months'] ?? 0) < 1) {
            throw new RuntimeException('Useful life must be at least one month.');
        }

        if (($input['depreciation_method'] ?? 'straight_line') !== 'straight_line') {
            throw new RuntimeException('Only straight-line depreciation is supported in this phase.');
        }
    }

    private function lockAsset(FixedAsset $asset): FixedAsset
    {
        return FixedAsset::withoutGlobalScopes()
            ->where('company_id', $this->requireCompanyId())
            ->whereKey($asset->id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function assertAccountsBelongToCompany(FixedAsset $asset, array $accountIds): void
    {
        foreach ($accountIds as $accountId) {
            $this->assertAccountBelongsToCompany((int) $accountId, $asset->company_id);
        }
    }

    private function assertAccountBelongsToCompany(int $accountId, int $companyId): void
    {
        $account = Account::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereKey($accountId)
            ->first();

        if (! $account) {
            throw new RuntimeException('All fixed asset accounts must belong to the current company.');
        }
    }

    private function nextNumber(int $companyId): string
    {
        $prefix = 'FA-' . now()->format('Y') . '-';
        $lastNumber = FixedAsset::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('number', 'like', $prefix . '%')
            ->orderByRaw('CAST(SUBSTR(number, ' . (strlen($prefix) + 1) . ') AS INTEGER) DESC')
            ->value('number');

        $nextSequence = $lastNumber ? ((int) substr($lastNumber, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad((string) $nextSequence, 6, '0', STR_PAD_LEFT);
    }

    private function requireCompanyId(): int
    {
        $companyId = $this->currentCompany->id();

        if ($companyId === null) {
            throw new RuntimeException('Cannot process a fixed asset without a resolved company context.');
        }

        return $companyId;
    }
}
