<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Seeds the base permission catalog and role -> permission matrix.
 *
 * Empty skeleton for the ERP fork — each module adds its own
 * `module.action` permissions here as it's built (see §8 of the plan).
 */
class RolePermissionSeeder extends Seeder
{
    private const PERMISSIONS = [
        'users.view'   => 'Melihat pengguna sistem',
        'users.create' => 'Membuat pengguna sistem',
        'users.edit'   => 'Mengubah pengguna sistem',
        'users.delete' => 'Menghapus pengguna sistem',

        'roles.view'   => 'Melihat role & hak akses',
        'roles.create' => 'Membuat role & hak akses',
        'roles.edit'   => 'Mengubah role & hak akses',
        'roles.delete' => 'Menghapus role & hak akses',

        'companies.view'   => 'Melihat data perusahaan',
        'companies.create' => 'Membuat perusahaan baru',
        'companies.edit'   => 'Mengubah data perusahaan',
        'companies.delete' => 'Menghapus perusahaan',
        'companies.manage' => 'Mengelola perusahaan (gabungan)',

        'accounts.view'   => 'Melihat bagan akun',
        'accounts.create' => 'Membuat akun baru',
        'accounts.edit'   => 'Mengubah akun',
        'accounts.delete' => 'Menghapus akun',
        'accounts.manage' => 'Mengelola bagan akun (gabungan)',

        'currencies.view' => 'Melihat master currency dan kurs',
        'currencies.manage' => 'Mengelola currency dan menyetujui kurs',

        'master-data.view'   => 'Melihat data master (UOM, gudang, pajak, supplier, customer, produk)',
        'master-data.manage' => 'Mengelola data master (gabungan)',

        'inventory.view'   => 'Melihat stok dan kartu stok',
        'inventory.manage' => 'Mengelola stok (opname, penyesuaian)',

        'manufacturing.view'   => 'Melihat BOM, routing, work order, dan MRP',
        'manufacturing.manage' => 'Mengelola BOM, routing, dan work order produksi',

        'quality.view'   => 'Melihat hasil inspeksi dan NCR',
        'quality.manage' => 'Mengelola inspeksi kualitas dan NCR',

        'purchasing.view'   => 'Melihat purchase request, PO, penerimaan barang, dan invoice supplier',
        'purchasing.manage' => 'Mengelola purchase request, PO, penerimaan barang, dan invoice supplier',

        'sales.view'   => 'Melihat sales order, delivery order, invoice, dan retur penjualan',
        'sales.manage' => 'Mengelola sales order, delivery order, invoice, dan retur penjualan',

        'cash-bank.view'   => 'Melihat rekening kas/bank, transaksi kas, dan rekonsiliasi bank',
        'cash-bank.manage' => 'Mengelola rekening kas/bank, transaksi kas, dan rekonsiliasi bank',

        'ar.view'   => 'Melihat penerimaan piutang (AR receipt)',
        'ar.manage' => 'Mengelola penerimaan piutang (AR receipt)',

        'ap.view'   => 'Melihat pembayaran hutang (AP payment)',
        'ap.manage' => 'Mengelola pembayaran hutang (AP payment)',

        'fixed-assets.view'   => 'Melihat daftar aset tetap dan depresiasi',
        'fixed-assets.manage' => 'Mengelola aset tetap, depresiasi, dan disposal',

        'reports.view' => 'Melihat laporan keuangan dan buku besar',
        'reports.export' => 'Mengekspor laporan ke PDF dan Excel',
        'reports.financial_statements' => 'Mengakses paket laporan keuangan standar',
        'documents.print' => 'Mencetak dokumen operasional',

        'audit.view' => 'Melihat riwayat perubahan dan persetujuan dokumen',

        'ai.use' => 'Menggunakan AI Copilot dan insight',
        'ai.execute' => 'Mengonfirmasi pembuatan draft melalui AI Copilot',
        'ai.documents.view' => 'Melihat dokumen OCR dan hasil ekstraksi',
        'ai.documents.manage' => 'Mengunggah, memproses, dan mereview dokumen OCR',

        'maintenance.view' => 'Melihat equipment dan maintenance',
        'maintenance.manage' => 'Mengelola equipment, jadwal, reading, dan work order maintenance',
        'maintenance.execute' => 'Menjalankan dan menyelesaikan work order maintenance',
    ];

    public function run(): void
    {
        foreach (self::PERMISSIONS as $name => $description) {
            Permission::updateOrCreate(
                ['name' => $name],
                [
                    'display_name' => $this->slugToTitle($name),
                    'description' => $description,
                ]
            );
        }

        $matrix = [
            'super_admin' => array_keys(self::PERMISSIONS),
        ];

        foreach (Role::whereNull('company_id')->get() as $role) {
            $perms = $matrix[$role->name] ?? null;
            if ($perms === null) {
                continue;
            }
            $permIds = Permission::whereIn('name', $perms)->pluck('id');
            $role->permissions()->sync($permIds);
        }
    }

    private function slugToTitle(string $slug): string
    {
        return ucwords(str_replace(['.', '-', '_'], ' ', $slug));
    }
}
