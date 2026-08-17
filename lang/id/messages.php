<?php

return [
    'company' => [
        'created' => 'Perusahaan berhasil dibuat.',
        'updated' => 'Perusahaan berhasil diperbarui.',
        'switched' => 'Perusahaan berhasil diganti.',
        'not_member' => 'Anda bukan anggota perusahaan tersebut.',
        'deleted' => 'Perusahaan berhasil dihapus.',
        'has_dependent_data' => 'Perusahaan tidak dapat dihapus karena masih memiliki data bisnis terkait.',
        'cannot_delete_only_company' => 'Anda tidak dapat menghapus satu-satunya perusahaan Anda.',
    ],

    'role' => [
        'created' => 'Role berhasil ditambahkan.',
        'updated' => 'Role berhasil diperbarui.',
        'deleted' => 'Role berhasil dihapus.',
        'cannot_delete_locked' => 'Role ":name" tidak dapat dihapus.',
        'cannot_delete_in_use' => 'Role tidak dapat dihapus karena masih digunakan di :count keanggotaan perusahaan.',
    ],

    'user' => [
        'created' => 'Pengguna berhasil ditambahkan.',
        'invited' => 'Pengguna berhasil ditambahkan ke perusahaan ini.',
        'updated' => 'Pengguna berhasil diperbarui.',
        'role_updated' => 'Peran pengguna berhasil diperbarui.',
        'removed' => 'Pengguna berhasil dihapus dari perusahaan.',
        'not_member' => 'Pengguna tersebut bukan anggota perusahaan ini.',
        'cannot_remove_self' => 'Anda tidak dapat menghapus akun Anda sendiri.',
        'cannot_remove_last_admin' => 'Tidak dapat menghapus admin terakhir perusahaan ini.',
    ],

    'uom' => [
        'created' => 'Satuan berhasil ditambahkan.',
        'updated' => 'Satuan berhasil diperbarui.',
        'deleted' => 'Satuan berhasil dihapus.',
        'cannot_delete_in_use' => 'Satuan tidak dapat dihapus karena masih digunakan oleh produk.',
    ],

    'warehouse' => [
        'created' => 'Gudang berhasil ditambahkan.',
        'updated' => 'Gudang berhasil diperbarui.',
        'deleted' => 'Gudang berhasil dihapus.',
        'cannot_delete_in_use' => 'Gudang tidak dapat dihapus karena masih digunakan oleh produk.',
    ],

    'tax_code' => [
        'created' => 'Kode pajak berhasil ditambahkan.',
        'updated' => 'Kode pajak berhasil diperbarui.',
        'deleted' => 'Kode pajak berhasil dihapus.',
    ],

    'supplier' => [
        'created' => 'Pemasok berhasil ditambahkan.',
        'updated' => 'Pemasok berhasil diperbarui.',
        'deleted' => 'Pemasok berhasil dihapus.',
    ],

    'customer' => [
        'created' => 'Pelanggan berhasil ditambahkan.',
        'updated' => 'Pelanggan berhasil diperbarui.',
        'deleted' => 'Pelanggan berhasil dihapus.',
    ],

    'product_category' => [
        'created' => 'Kategori produk berhasil ditambahkan.',
        'updated' => 'Kategori produk berhasil diperbarui.',
        'deleted' => 'Kategori produk berhasil dihapus.',
        'cannot_delete_in_use' => 'Kategori tidak dapat dihapus karena masih digunakan oleh produk.',
    ],

    'product' => [
        'created' => 'Produk berhasil ditambahkan.',
        'updated' => 'Produk berhasil diperbarui.',
        'deleted' => 'Produk berhasil dihapus.',
    ],

    'account' => [
        'created' => 'Akun berhasil ditambahkan.',
        'updated' => 'Akun berhasil diperbarui.',
        'deleted' => 'Akun berhasil dihapus.',
        'cannot_be_own_parent' => 'Akun tidak dapat menjadi induk dari dirinya sendiri.',
        'cannot_delete_has_children' => 'Akun tidak dapat dihapus karena masih memiliki sub-akun.',
        'cannot_delete_in_use' => 'Akun tidak dapat dihapus karena sudah memiliki transaksi jurnal.',
    ],

    'stock_opname' => [
        'created' => 'Stock opname berhasil dibuat.',
        'lines_saved' => 'Hasil hitung fisik berhasil disimpan.',
        'completed' => 'Stock opname berhasil diselesaikan, stok telah disesuaikan.',
        'not_draft' => 'Stock opname ini sudah selesai dan tidak dapat diubah lagi.',
        'no_counted_lines' => 'Belum ada baris yang diisi jumlah hitung fisiknya.',
        'adjustment_note' => 'Penyesuaian dari stock opname :number',
    ],

    'work_center' => [
        'created' => 'Work center berhasil ditambahkan.',
        'updated' => 'Work center berhasil diperbarui.',
        'deleted' => 'Work center berhasil dihapus.',
        'cannot_delete_in_use' => 'Work center tidak dapat dihapus karena masih digunakan di routing.',
    ],

    'bom' => [
        'created' => 'BOM berhasil ditambahkan.',
        'updated' => 'BOM berhasil diperbarui.',
        'deleted' => 'BOM berhasil dihapus.',
        'cannot_delete_in_use' => 'BOM tidak dapat dihapus karena sedang aktif digunakan produk.',
    ],

    'routing' => [
        'created' => 'Routing berhasil ditambahkan.',
        'updated' => 'Routing berhasil diperbarui.',
        'deleted' => 'Routing berhasil dihapus.',
        'cannot_delete_in_use' => 'Routing tidak dapat dihapus karena sedang aktif digunakan produk.',
    ],

    'production_order' => [
        'created' => 'Production order berhasil dibuat.',
        'missing_bom_routing' => 'Produk ini belum memiliki BOM dan Routing aktif.',
        'released' => 'Production order berhasil dirilis.',
        'materials_issued' => 'Material berhasil dikeluarkan untuk produksi.',
        'operation_started' => 'Operasi berhasil dimulai.',
        'operation_completed' => 'Operasi berhasil diselesaikan.',
        'completed' => 'Production order berhasil diselesaikan, barang jadi masuk stok.',
        'submitted_for_qc' => 'Production order berhasil dikirim untuk inspeksi QC.',
    ],

    'quality' => [
        'order_not_pending_qc' => 'Production order ini belum berstatus menunggu QC.',
        'passed_exceeds_inspected' => 'Kuantitas lulus tidak boleh melebihi kuantitas yang diinspeksi.',
        'final_inspection_recorded' => 'Inspeksi akhir berhasil dicatat, barang jadi telah diproses sesuai hasil QC.',
    ],

    'ncr' => [
        'disposition_recorded' => 'Disposisi NCR berhasil dicatat.',
        'corrective_action_recorded' => 'Tindakan korektif berhasil dicatat.',
        'closed' => 'NCR berhasil ditutup.',
    ],

    'purchase_request' => [
        'created' => 'Purchase request berhasil dibuat.',
        'submitted' => 'Purchase request berhasil diajukan untuk persetujuan.',
        'approved' => 'Purchase request berhasil disetujui.',
        'rejected' => 'Purchase request ditolak.',
    ],

    'purchase_order' => [
        'created' => 'Purchase order berhasil dibuat dari purchase request.',
        'submitted_for_approval' => 'Purchase order berhasil diajukan untuk persetujuan.',
        'approved' => 'Purchase order berhasil disetujui.',
        'sent' => 'Purchase order berhasil dikirim ke supplier.',
        'closed' => 'Purchase order berhasil ditutup.',
    ],

    'goods_receipt' => [
        'created' => 'Penerimaan barang berhasil dicatat, menunggu inspeksi kualitas.',
        'put_away' => 'Inspeksi kualitas selesai, barang yang lolos telah masuk stok.',
    ],

    'supplier_invoice' => [
        'created' => 'Invoice supplier berhasil dicatat.',
    ],

    'sales_order' => [
        'created' => 'Sales order berhasil dibuat.',
        'submitted_for_approval' => 'Sales order berhasil diajukan untuk persetujuan.',
        'approved' => 'Sales order berhasil disetujui.',
        'closed' => 'Sales order berhasil ditutup.',
    ],

    'delivery_order' => [
        'created' => 'Delivery order berhasil dibuat.',
        'shipped' => 'Barang berhasil dikirim, stok dan jurnal COGS telah diposting.',
    ],

    'sales_invoice' => [
        'created' => 'Invoice penjualan berhasil dibuat dan diposting.',
    ],

    'sales_return' => [
        'created' => 'Retur penjualan berhasil dicatat, stok dan jurnal telah disesuaikan.',
    ],
];
