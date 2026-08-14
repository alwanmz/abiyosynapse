<?php

return [
    // Berkas PDF utama untuk guidebook bertipe "pdf".
    'pdf' => [
        'allowed_mimes' => ['pdf'],
        'max_kb' => 20 * 1024,
    ],

    // Lampiran pendukung, berlaku untuk semua tipe konten.
    'attachments' => [
        'allowed_mimes' => ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx', 'xls', 'xlsx'],
        'max_kb' => 10 * 1024,
        'max_files' => 10,
    ],
];
