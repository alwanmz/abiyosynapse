<?php

return [
    'company' => [
        'created' => 'Perusahaan berhasil dibuat.',
        'updated' => 'Perusahaan berhasil diperbarui.',
        'switched' => 'Perusahaan berhasil diganti.',
        'not_member' => 'Anda bukan anggota perusahaan tersebut.',
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
        'updated' => 'Pengguna berhasil diperbarui.',
        'role_updated' => 'Peran pengguna berhasil diperbarui.',
        'removed' => 'Pengguna berhasil dihapus dari perusahaan.',
        'not_member' => 'Pengguna tersebut bukan anggota perusahaan ini.',
        'cannot_remove_self' => 'Anda tidak dapat menghapus akun Anda sendiri.',
        'cannot_remove_last_admin' => 'Tidak dapat menghapus admin terakhir perusahaan ini.',
    ],
];
