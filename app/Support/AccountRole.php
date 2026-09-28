<?php

namespace App\Support;

/**
 * System roles an account can play in automatic postings. Every company maps
 * each role to one of its own accounts, so the chart of accounts can use any
 * codes and names.
 */
enum AccountRole: string
{
    case CashParent = 'cash_parent';
    case BankParent = 'bank_parent';
    case AccountsReceivable = 'accounts_receivable';
    case RawMaterialInventory = 'raw_material_inventory';
    case WipInventory = 'wip_inventory';
    case FinishedGoodsInventory = 'finished_goods_inventory';
    case InputTax = 'input_tax';
    case AccountsPayable = 'accounts_payable';
    case Grni = 'grni';
    case OutputTax = 'output_tax';
    case SalesRevenue = 'sales_revenue';
    case AssetDisposalGain = 'asset_disposal_gain';
    case FxGain = 'fx_gain';
    case Cogs = 'cogs';
    case ScrapExpense = 'scrap_expense';
    case FxRealizedLoss = 'fx_realized_loss';
    case FxUnrealizedLoss = 'fx_unrealized_loss';

    public function label(): string
    {
        return match ($this) {
            self::CashParent => 'Induk Akun Kas',
            self::BankParent => 'Induk Akun Bank',
            self::AccountsReceivable => 'Piutang Usaha',
            self::RawMaterialInventory => 'Persediaan Bahan Baku / Barang Dagang',
            self::WipInventory => 'Persediaan Barang Dalam Proses',
            self::FinishedGoodsInventory => 'Persediaan Barang Jadi',
            self::InputTax => 'PPN Masukan',
            self::AccountsPayable => 'Utang Usaha',
            self::Grni => 'Barang Diterima Belum Ditagih (GRNI)',
            self::OutputTax => 'PPN Keluaran',
            self::SalesRevenue => 'Pendapatan Penjualan',
            self::AssetDisposalGain => 'Keuntungan Pelepasan Aset Tetap',
            self::FxGain => 'Keuntungan Selisih Kurs',
            self::Cogs => 'Harga Pokok Penjualan',
            self::ScrapExpense => 'Beban Scrap / Penyesuaian Persediaan',
            self::FxRealizedLoss => 'Kerugian Selisih Kurs (Realisasi)',
            self::FxUnrealizedLoss => 'Kerugian Revaluasi Kurs',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::CashParent => 'Akun kas baru dibuat sebagai sub-akun dari akun ini.',
            self::BankParent => 'Akun rekening bank baru dibuat sebagai sub-akun dari akun ini.',
            self::AccountsReceivable => 'Dipakai saat faktur penjualan dan penerimaan piutang.',
            self::RawMaterialInventory => 'Dipakai saat penerimaan barang dari pemasok dan produksi.',
            self::WipInventory => 'Dipakai saat produksi berjalan.',
            self::FinishedGoodsInventory => 'Dipakai saat pengiriman, faktur, dan hasil produksi.',
            self::InputTax => 'Dipakai saat faktur pemasok ber-PPN.',
            self::AccountsPayable => 'Dipakai saat faktur pemasok dan pembayaran utang.',
            self::Grni => 'Penampung barang yang sudah diterima tapi belum ditagih pemasok.',
            self::OutputTax => 'Dipakai saat faktur penjualan ber-PPN.',
            self::SalesRevenue => 'Dipakai saat faktur dan retur penjualan.',
            self::AssetDisposalGain => 'Dipakai saat aset tetap dijual di atas nilai buku.',
            self::FxGain => 'Dipakai saat ada keuntungan selisih kurs.',
            self::Cogs => 'Dipakai saat barang terjual.',
            self::ScrapExpense => 'Dipakai saat barang ditolak QC atau dibuang.',
            self::FxRealizedLoss => 'Dipakai saat pelunasan dengan kerugian kurs.',
            self::FxUnrealizedLoss => 'Dipakai saat revaluasi kurs akhir periode.',
        };
    }

    /**
     * Parent roles hold cash/bank sub-accounts and may point at a header
     * account; every other role must point at a postable account.
     */
    public function isParentRole(): bool
    {
        return in_array($this, [self::CashParent, self::BankParent], true);
    }

    /** @return array<int, string> account types this role may map to */
    public function allowedTypes(): array
    {
        return match ($this) {
            self::CashParent, self::BankParent, self::AccountsReceivable, self::RawMaterialInventory,
            self::WipInventory, self::FinishedGoodsInventory, self::InputTax => ['asset'],
            self::AccountsPayable, self::Grni, self::OutputTax => ['liability'],
            self::SalesRevenue, self::AssetDisposalGain, self::FxGain => ['revenue'],
            self::Cogs, self::ScrapExpense, self::FxRealizedLoss, self::FxUnrealizedLoss => ['expense'],
        };
    }

    /** @return array<int, array{value: string, label: string, description: string, is_parent_role: bool, allowed_types: array<int, string>}> */
    public static function options(): array
    {
        return array_map(fn (self $role): array => [
            'value' => $role->value,
            'label' => $role->label(),
            'description' => $role->description(),
            'is_parent_role' => $role->isParentRole(),
            'allowed_types' => $role->allowedTypes(),
        ], self::cases());
    }
}
