<?php

// Panel /app (mahasiswa mandiri). Bahasa sederhana untuk pembaca asing (R-2.13).
return [
    'home' => [
        'greeting' => 'Selamat datang, :name',
        'subheading_new' => 'Daftar program non-gelar di Universitas Udayana dalam beberapa langkah.',
        'subheading' => 'Pantau pendaftaran Anda dan lakukan langkah berikutnya di sini.',
        'your_application' => 'Pendaftaran Anda',
        'view_application' => 'Lihat pendaftaran',
        'see_programs' => 'Lihat program',
        'period_until' => ':period, ditutup :date',
        'no_period' => 'Belum ada periode yang dibuka',
        'prepare_title' => 'Yang perlu disiapkan',
        'prepare_desc' => 'Siapkan berkas ini sebelum mulai. Ukuran tiap berkas maks. 300 KB.',
        'prepare_passport' => 'Paspor yang masih berlaku (nomor, tanggal terbit, dan tanggal habis berlaku).',
        'stages' => [
            'start' => ['title' => 'Mulai pendaftaran', 'body' => 'Pilih program, isi data diri, lalu unggah dokumen. Anda bisa menyimpan draf dan melanjutkannya kapan saja.'],
            'complete' => ['title' => 'Lengkapi pendaftaran', 'body' => 'Masih ada :count butir yang perlu dilengkapi sebelum pendaftaran bisa diajukan.', 'cta' => 'Lanjutkan'],
            'submit' => ['title' => 'Siap diajukan', 'body' => 'Semua butir sudah lengkap. Periksa sekali lagi, lalu ajukan pendaftaran ke KUI.', 'cta' => 'Ajukan pendaftaran'],
            'waiting' => ['title' => 'Sedang diperiksa KUI', 'body' => 'KUI sedang memeriksa pendaftaran Anda. Anda akan menerima notifikasi saat ada keputusan.'],
            'revision' => ['title' => 'Perlu diperbaiki', 'body' => 'KUI meminta perbaikan. Perbaiki dokumen yang ditandai, lalu ajukan lagi.', 'cta' => 'Perbaiki sekarang'],
            'pay' => ['title' => 'Pendaftaran disetujui — silakan bayar', 'body' => 'Belum dibayar: :fees. Transfer ke nomor VA lalu unggah bukti transfernya.', 'cta' => 'Bayar & unggah bukti'],
            'payment_review' => ['title' => 'Pembayaran sedang diperiksa', 'body' => 'KUI sedang memeriksa bukti bayar Anda. LOA terbit setelah semua biaya terverifikasi.'],
            'loa' => ['title' => 'LOA sudah terbit', 'body' => 'Selamat! Letter of Acceptance Anda sudah bisa diunduh.'],
        ],
    ],

    'application' => [
        'title' => 'Pendaftaran saya',
        'start_title' => 'Mulai pendaftaran',
        'start_description' => 'Isian bertanda * cukup untuk menyimpan draf. Isian lain bisa dilengkapi nanti dan diperiksa saat pendaftaran diajukan.',
        'program_step_hint' => 'Program yang sedang dibuka',
        'program_hint' => 'Hanya program yang periode pendaftarannya sedang dibuka.',
        'consent' => 'Saya menyatakan data ini benar dan menyetujui data saya diproses oleh KUI Universitas Udayana untuk pendaftaran.',
        'saved' => 'Data disimpan',
        'actions' => [
            'start' => 'Mulai pendaftaran',
        ],
    ],

    'programs' => [
        'title' => 'Program',
        'description' => 'Program non-gelar yang periode pendaftarannya sedang dibuka.',
        'open_title' => 'Program yang sedang dibuka',
        'empty' => 'Belum ada program yang dibuka',
        'empty_body' => 'Periksa kembali nanti, atau hubungi KUI Universitas Udayana.',
        'period' => ':period · ditutup :date',
        'choose' => 'Pilih program ini',
        'chosen' => 'Program pilihan Anda',
        'change_heading' => 'Ganti ke :program?',
        'change_description' => 'Program pendaftaran Anda akan diganti. Data dan dokumen yang sudah diunggah tetap tersimpan.',
        'changed' => 'Program diganti ke :program',
    ],
];
