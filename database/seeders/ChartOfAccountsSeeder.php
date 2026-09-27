<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Company;
use App\Services\CurrentCompany;
use Illuminate\Database\Seeder;

/**
 * Seeds a standard Indonesian chart of accounts (Aset/Kewajiban/Modal/
 * Pendapatan/Beban, berjenjang) for the default demo company. Real
 * companies will get their own catalog via the Fase 1 Account CRUD UI —
 * this seeder exists for local dev / demo data only.
 */
class ChartOfAccountsSeeder extends Seeder
{
    /**
     * Each row: code, name, type, normal_balance, is_postable, parent code (or null for top-level).
     *
     * @var array<int, array{0: string, 1: string, 2: string, 3: string, 4: bool, 5: ?string}>
     */
    private const ACCOUNTS = [
        // Aset
        ['1', 'Aset', 'asset', 'debit', false, null],
        ['1.1', 'Aset Lancar', 'asset', 'debit', false, '1'],
        ['1.1.1', 'Kas', 'asset', 'debit', true, '1.1'],
        ['1.1.2', 'Bank', 'asset', 'debit', true, '1.1'],
        ['1.1.3', 'Piutang Usaha', 'asset', 'debit', true, '1.1'],
        ['1.1.4', 'Persediaan Bahan Baku', 'asset', 'debit', true, '1.1'],
        ['1.1.5', 'Persediaan Barang Dalam Proses (WIP)', 'asset', 'debit', true, '1.1'],
        ['1.1.6', 'Persediaan Barang Jadi', 'asset', 'debit', true, '1.1'],
        ['1.1.7', 'PPN Masukan', 'asset', 'debit', true, '1.1'],
        ['1.2', 'Aset Tetap', 'asset', 'debit', false, '1'],
        ['1.2.1', 'Peralatan & Mesin', 'asset', 'debit', true, '1.2'],
        ['1.2.2', 'Akumulasi Penyusutan', 'asset', 'debit', true, '1.2'],

        // Kewajiban
        ['2', 'Kewajiban', 'liability', 'credit', false, null],
        ['2.1', 'Kewajiban Lancar', 'liability', 'credit', false, '2'],
        ['2.1.1', 'Utang Usaha', 'liability', 'credit', true, '2.1'],
        ['2.1.2', 'Utang Barang Diterima Belum Ditagih (GRNI)', 'liability', 'credit', true, '2.1'],
        ['2.1.3', 'PPN Keluaran', 'liability', 'credit', true, '2.1'],

        // Modal
        ['3', 'Modal', 'equity', 'credit', false, null],
        ['3.1', 'Modal Disetor', 'equity', 'credit', true, '3'],
        ['3.2', 'Laba Ditahan', 'equity', 'credit', true, '3'],

        // Pendapatan
        ['4', 'Pendapatan', 'revenue', 'credit', false, null],
        ['4.1', 'Pendapatan Penjualan', 'revenue', 'credit', true, '4'],
        ['4.2', 'Keuntungan Selisih Kurs', 'revenue', 'credit', true, '4'],

        // Beban
        ['5', 'Beban', 'expense', 'debit', false, null],
        ['5.1', 'Harga Pokok Penjualan (COGS)', 'expense', 'debit', true, '5'],
        ['5.2', 'Beban Tenaga Kerja Langsung', 'expense', 'debit', true, '5'],
        ['5.3', 'Beban Overhead Pabrik', 'expense', 'debit', true, '5'],
        ['5.4', 'Beban Scrap / Penyesuaian Persediaan', 'expense', 'debit', true, '5'],
        ['5.5', 'Beban Operasional', 'expense', 'debit', true, '5'],
        ['5.6', 'Kerugian Selisih Kurs', 'expense', 'debit', true, '5'],
        ['5.7', 'Kerugian Revaluasi Kurs', 'expense', 'debit', true, '5'],
    ];

    public function run(): void
    {
        $company = Company::where('code', 'default')->first();

        if (! $company) {
            return;
        }

        $context = app(CurrentCompany::class);
        $previousCompany = $context->get();
        $context->set($company);

        try {
            $idsByCode = [];

            foreach (self::ACCOUNTS as [$code, $name, $type, $normalBalance, $isPostable, $parentCode]) {
                $account = Account::updateOrCreate(
                    ['company_id' => $company->id, 'code' => $code],
                    [
                        'name' => $name,
                        'type' => $type,
                        'normal_balance' => $normalBalance,
                        'is_postable' => $isPostable,
                        'parent_id' => $parentCode ? ($idsByCode[$parentCode] ?? null) : null,
                        'is_active' => true,
                    ]
                );

                $idsByCode[$code] = $account->id;
            }
        } finally {
            $context->set($previousCompany);
        }
    }
}
