<?php

return [

    'meta' => [
        'title' => 'Buat akun',
    ],

    'brand' => [
        'system_name' => 'Sistem Informasi Penerimaan Mahasiswa Asing Non‑Degree',
        'office' => 'Kantor Urusan Internasional · Universitas Udayana',
        'logo_alt' => 'Logo Universitas Udayana',
    ],

    'skip_to_content' => 'Langsung ke konten utama',

    // Nama bahasa ditulis dalam bahasanya sendiri (endonim) di kedua file
    'language' => [
        'label' => 'Bahasa',
        'en' => 'English',
        'id' => 'Bahasa Indonesia',
    ],

    'footer' => [
        'copyright' => '© :year Universitas Udayana',
    ],

    'register' => [
        'title' => 'Buat akun SIPMA',
        'role_legend' => 'Anda mendaftar sebagai?',
        'roles' => [
            'student' => [
                'title' => 'Mahasiswa',
            ],
            'agent' => [
                'title' => 'Agen mitra',
            ],
        ],
        'fields' => [
            'name' => 'Nama lengkap',
            'name_hint' => 'Sesuai yang tertulis di paspor.',
            'contact_name' => 'Narahubung',
            'email' => 'Alamat email',
            'password' => 'Kata sandi',
            'password_hint' => 'Minimal 8 karakter.',
            'password_confirmation' => 'Ulangi kata sandi',
            'agency_name' => 'Nama agen',
            'country' => 'Negara',
        ],
        'agency' => [
            'title' => 'Tentang agen Anda',
            'text' => 'Setelah ini, Anda mengunggah MOU. Kantor Urusan Internasional memeriksanya sebelum Anda dapat mendaftarkan mahasiswa.',
        ],
        'consent' => 'Saya setuju Universitas Udayana memproses data pribadi yang saya kirimkan untuk keperluan pendaftaran.',
        'submit' => 'Buat akun',
        'submitting' => 'Membuat akun Anda…',
        'have_account' => 'Sudah punya akun?',
        'login' => 'Masuk',
        'error_summary' => 'Ada yang perlu diperbaiki pada pendaftaran Anda',
        'error_prefix' => 'Kesalahan:',
        'throttled' => 'Terlalu banyak percobaan pendaftaran. Tunggu :seconds detik lalu coba lagi.',
    ],

    // Tombol ikon mata pada field password
    'password' => [
        'show' => 'Tampilkan kata sandi',
        'hide' => 'Sembunyikan kata sandi',
    ],

    // Login terpadu untuk semua role
    'login' => [
        'meta_title' => 'Masuk',
        'title' => 'Masuk ke SIPMA',
        'subtitle' => 'Untuk mahasiswa, agen mitra, dan staf universitas.',
        'email' => 'Alamat email',
        'password' => 'Kata sandi',
        'remember' => 'Tetap masuk',
        'forgot' => 'Lupa kata sandi?',
        'submit' => 'Masuk',
        'submitting' => 'Sedang masuk…',
        'no_account' => 'Belum punya akun?',
        'register' => 'Buat akun',
        'error_summary' => 'Kami tidak dapat memasukkan Anda',
        'failed' => 'Alamat email atau kata sandi salah.',
        'inactive' => 'Akun ini dinonaktifkan. Hubungi Kantor Urusan Internasional untuk bantuan.',
        'throttled' => 'Terlalu banyak percobaan masuk. Tunggu :seconds detik lalu coba lagi.',
        'validation' => [
            'email_required' => 'Masukkan alamat email Anda.',
            'email_email' => 'Masukkan alamat email yang valid, misalnya nama@contoh.com.',
            'password_required' => 'Masukkan kata sandi Anda.',
        ],
    ],

    'validation' => [
        'role' => 'Pilih apakah Anda mendaftar sebagai mahasiswa atau agen mitra.',
        'name_required' => 'Masukkan nama lengkap Anda.',
        'email_required' => 'Masukkan alamat email Anda.',
        'email_email' => 'Masukkan alamat email yang valid, misalnya nama@contoh.com.',
        'email_unique' => 'Alamat email ini sudah terdaftar. Silakan masuk.',
        'password_required' => 'Buat kata sandi.',
        'password_min' => 'Kata sandi minimal :min karakter.',
        'password_confirmation_required' => 'Ketik ulang kata sandi Anda.',
        'password_mismatch' => 'Kata sandi tidak sama. Ketik kata sandi yang sama di kedua kolom.',
        'agency_name_required' => 'Masukkan nama agen Anda.',
        'country_required' => 'Masukkan negara tempat agen Anda berada.',
        'consent' => 'Untuk membuat akun, Anda perlu menyetujui cara kami memproses data pribadi Anda.',
        'max' => 'Maksimal :max karakter.',
    ],

];
