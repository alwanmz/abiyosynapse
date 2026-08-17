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
];
