<?php

namespace App\Support;

/**
 * The standard Indonesian chart of accounts, the financial-report lines an
 * account can be mapped to, and the default system-role mapping. Used by the
 * seeders, as the "COA standar" onboarding option, and as the AI fallback.
 */
final class DefaultChartOfAccounts
{
    /** @var array<string, array<string, string>> report code => [line code => label] */
    public const REPORT_LINES = [
        'balance_sheet' => [
            'cash' => 'Kas',
            'bank' => 'Bank',
            'trade_receivables' => 'Piutang Usaha',
            'other_receivables' => 'Piutang Lain-lain',
            'inventory' => 'Persediaan Barang Dagang',
            'raw_material_inventory' => 'Persediaan Bahan Baku',
            'work_in_progress' => 'Persediaan Barang Dalam Proses',
            'finished_goods_inventory' => 'Persediaan Barang Jadi',
            'input_tax' => 'Pajak Dibayar di Muka',
            'prepaid_expenses' => 'Biaya Dibayar di Muka',
            'other_current_assets' => 'Aset Lancar Lainnya',
            'property_plant_equipment' => 'Aset Tetap',
            'accumulated_depreciation' => 'Akumulasi Penyusutan',
            'intangible_assets' => 'Aset Tak Berwujud',
            'other_non_current_assets' => 'Aset Tidak Lancar Lainnya',
            'trade_payables' => 'Utang Usaha',
            'grni' => 'Utang Barang Diterima Belum Ditagih',
            'output_tax' => 'Utang Pajak Pertambahan Nilai',
            'tax_payables' => 'Utang Pajak Lainnya',
            'accrued_expenses' => 'Beban Masih Harus Dibayar',
            'other_current_liabilities' => 'Liabilitas Jangka Pendek Lainnya',
            'long_term_liabilities' => 'Liabilitas Jangka Panjang',
            'paid_in_capital' => 'Modal Disetor',
            'retained_earnings' => 'Laba Ditahan',
            'other_equity' => 'Ekuitas Lainnya',
        ],
        'profit_loss' => [
            'revenue' => 'Pendapatan Usaha',
            'other_revenue' => 'Pendapatan Lain-lain',
            'foreign_exchange_gain' => 'Keuntungan Selisih Kurs',
            'cost_of_sales' => 'Beban Pokok Pendapatan',
            'direct_labour' => 'Beban Tenaga Kerja Langsung',
            'factory_overhead' => 'Beban Overhead Pabrik',
            'scrap_expense' => 'Beban Scrap',
            'selling_expenses' => 'Beban Penjualan',
            'administrative_expenses' => 'Beban Umum & Administrasi',
            'operating_expenses' => 'Beban Operasional',
            'depreciation_expense' => 'Beban Penyusutan',
            'other_expenses' => 'Beban Lain-lain',
            'foreign_exchange_loss' => 'Kerugian Selisih Kurs',
            'revaluation_loss' => 'Kerugian Revaluasi Kurs',
            'income_tax_expense' => 'Beban Pajak Penghasilan',
        ],
    ];

    /**
     * code, name, type, normal_balance, is_postable, parent code, report line
     *
     * @var array<int, array{0: string, 1: string, 2: string, 3: string, 4: bool, 5: ?string, 6: ?string}>
     */
    private const ACCOUNTS = [
        ['1', 'Aset', 'asset', 'debit', false, null, null],
        ['1.1', 'Aset Lancar', 'asset', 'debit', false, '1', null],
        ['1.1.1', 'Kas', 'asset', 'debit', true, '1.1', 'cash'],
        ['1.1.2', 'Bank', 'asset', 'debit', true, '1.1', 'bank'],
        ['1.1.3', 'Piutang Usaha', 'asset', 'debit', true, '1.1', 'trade_receivables'],
        ['1.1.4', 'Persediaan Bahan Baku', 'asset', 'debit', true, '1.1', 'raw_material_inventory'],
        ['1.1.5', 'Persediaan Barang Dalam Proses (WIP)', 'asset', 'debit', true, '1.1', 'work_in_progress'],
        ['1.1.6', 'Persediaan Barang Jadi', 'asset', 'debit', true, '1.1', 'finished_goods_inventory'],
        ['1.1.7', 'PPN Masukan', 'asset', 'debit', true, '1.1', 'input_tax'],
        ['1.2', 'Aset Tetap', 'asset', 'debit', false, '1', null],
        ['1.2.1', 'Peralatan & Mesin', 'asset', 'debit', true, '1.2', 'property_plant_equipment'],
        ['1.2.2', 'Akumulasi Penyusutan', 'asset', 'debit', true, '1.2', 'accumulated_depreciation'],

        ['2', 'Kewajiban', 'liability', 'credit', false, null, null],
        ['2.1', 'Kewajiban Lancar', 'liability', 'credit', false, '2', null],
        ['2.1.1', 'Utang Usaha', 'liability', 'credit', true, '2.1', 'trade_payables'],
        ['2.1.2', 'Utang Barang Diterima Belum Ditagih (GRNI)', 'liability', 'credit', true, '2.1', 'grni'],
        ['2.1.3', 'PPN Keluaran', 'liability', 'credit', true, '2.1', 'output_tax'],

        ['3', 'Modal', 'equity', 'credit', false, null, null],
        ['3.1', 'Modal Disetor', 'equity', 'credit', true, '3', 'paid_in_capital'],
        ['3.2', 'Laba Ditahan', 'equity', 'credit', true, '3', 'retained_earnings'],

        ['4', 'Pendapatan', 'revenue', 'credit', false, null, null],
        ['4.1', 'Pendapatan Penjualan', 'revenue', 'credit', true, '4', 'revenue'],
        ['4.2', 'Keuntungan Selisih Kurs', 'revenue', 'credit', true, '4', 'foreign_exchange_gain'],

        ['5', 'Beban', 'expense', 'debit', false, null, null],
        ['5.1', 'Harga Pokok Penjualan (COGS)', 'expense', 'debit', true, '5', 'cost_of_sales'],
        ['5.2', 'Beban Tenaga Kerja Langsung', 'expense', 'debit', true, '5', 'direct_labour'],
        ['5.3', 'Beban Overhead Pabrik', 'expense', 'debit', true, '5', 'factory_overhead'],
        ['5.4', 'Beban Scrap / Penyesuaian Persediaan', 'expense', 'debit', true, '5', 'scrap_expense'],
        ['5.5', 'Beban Operasional', 'expense', 'debit', true, '5', 'operating_expenses'],
        ['5.6', 'Kerugian Selisih Kurs', 'expense', 'debit', true, '5', 'foreign_exchange_loss'],
        ['5.7', 'Kerugian Revaluasi Kurs', 'expense', 'debit', true, '5', 'revaluation_loss'],
    ];

    /** @var array<string, string> role => account code in the standard chart */
    private const ROLE_CODES = [
        'cash_parent' => '1.1.1',
        'bank_parent' => '1.1.2',
        'accounts_receivable' => '1.1.3',
        'raw_material_inventory' => '1.1.4',
        'wip_inventory' => '1.1.5',
        'finished_goods_inventory' => '1.1.6',
        'input_tax' => '1.1.7',
        'accounts_payable' => '2.1.1',
        'grni' => '2.1.2',
        'output_tax' => '2.1.3',
        'sales_revenue' => '4.1',
        'asset_disposal_gain' => '4.1',
        'fx_gain' => '4.2',
        'cogs' => '5.1',
        'scrap_expense' => '5.4',
        'fx_realized_loss' => '5.6',
        'fx_unrealized_loss' => '5.7',
    ];

    /**
     * @return array<int, array{code: string, name: string, type: string, normal_balance: string, is_postable: bool, parent_code: ?string, report_line: ?string}>
     */
    public static function accounts(): array
    {
        return array_map(fn (array $row): array => [
            'code' => $row[0],
            'name' => $row[1],
            'type' => $row[2],
            'normal_balance' => $row[3],
            'is_postable' => $row[4],
            'parent_code' => $row[5],
            'report_line' => $row[6],
        ], self::ACCOUNTS);
    }

    /** @return array<string, string> role => account code */
    public static function roleMapping(): array
    {
        return self::ROLE_CODES;
    }

    public static function reportCodeFor(string $lineCode): ?string
    {
        foreach (self::REPORT_LINES as $reportCode => $lines) {
            if (array_key_exists($lineCode, $lines)) {
                return $reportCode;
            }
        }

        return null;
    }

    /** @return array<int, string> */
    public static function lineCodes(): array
    {
        return array_merge(...array_map('array_keys', array_values(self::REPORT_LINES)));
    }

    /** @return array<int, array{value: string, label: string, report: string}> */
    public static function reportLineOptions(): array
    {
        $options = [];
        foreach (self::REPORT_LINES as $reportCode => $lines) {
            foreach ($lines as $value => $label) {
                $options[] = ['value' => $value, 'label' => $label, 'report' => $reportCode];
            }
        }

        return $options;
    }
}
