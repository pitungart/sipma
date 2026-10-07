<?php

// Teks alur pendaftaran (StudentWorkflow, MouWorkflow, checklist, notifikasi, garis waktu).
return [

    'documents' => [
        'format' => ':types · maks. :max KB',
        'photo_rules' => 'Foto formal 3×4 berlatar biru.',
    ],

    'fields' => [
        'full_name' => 'Nama lengkap (sesuai paspor)',
        'gender' => 'Jenis kelamin',
        'place_of_birth' => 'Tempat lahir',
        'date_of_birth' => 'Tanggal lahir',
        'nationality_code' => 'Kewarganegaraan',
        'email' => 'Email',
        'phone_number' => 'Nomor telepon',
        'permanent_address' => 'Alamat tetap',
        'home_university' => 'Universitas asal',
        'home_university_country_code' => 'Negara universitas asal',
        'passport_number' => 'Nomor paspor',
        'date_of_issued_passport' => 'Tanggal terbit paspor',
        'date_of_passport_expiry' => 'Tanggal habis berlaku paspor',
        'program_id' => 'Program',
    ],

    'actions' => [
        'upload_document' => 'mengunggah dokumen',
        'submit' => 'mengajukan pendaftaran',
        'pay' => 'mengunggah bukti bayar',
        'start_review' => 'memulai verifikasi',
        'review_document' => 'memeriksa dokumen',
        'request_revision' => 'meminta revisi',
        'approve' => 'menyetujui pendaftaran',
        'issue_loa' => 'menerbitkan LOA',
    ],

    'errors' => [
        'transition' => 'Tidak bisa :action selagi status pendaftaran “:status”.',
        'incomplete' => 'Lengkapi :count butir lagi sebelum mengajukan.',
        'document_locked' => ':document sudah disetujui dan tidak bisa diganti.',
        'payment_not_required' => ':type tidak dikenakan untuk program ini.',
        'payment_already_verified' => ':type sudah terverifikasi.',
        'invalid_review' => 'Pilih setujui, revisi, atau tolak untuk dokumen ini.',
        'note_required' => 'Tulis alasannya agar pendaftar tahu apa yang perlu diperbaiki.',
        'revision_reason_required' => 'Tandai minimal satu dokumen untuk direvisi atau tulis catatan umum.',
        'documents_not_approved' => 'Setujui dulu dokumen berikut: :documents.',
        'payment_not_pending' => 'Pembayaran ini sudah diproses.',
        'payments_outstanding' => 'LOA bisa diterbitkan setelah pembayaran berikut terverifikasi: :types.',
        'mou_already_approved' => 'MOU Anda sudah disetujui.',
        'mou_already_pending' => 'MOU Anda masih menunggu diperiksa.',
        'mou_not_pending' => 'MOU ini sudah diproses.',
    ],

    'notifications' => [
        'open' => 'Buka SIPMA',
        'greeting' => 'Halo :name,',
        'salutation' => 'Kantor Urusan Internasional, Universitas Udayana',
        'submitted' => [
            'title' => 'Pendaftaran baru',
            'body' => ':name mendaftar ke :program:again. Menunggu diverifikasi.',
            'again' => ' kembali setelah revisi',
        ],
        'revision' => [
            'title' => 'Pendaftaran perlu diperbaiki',
            'body' => 'Pendaftaran :name perlu beberapa perbaikan sebelum bisa dilanjutkan. :note',
            'see_documents' => 'Buka dokumen untuk melihat yang perlu diperbaiki.',
        ],
        'approved' => [
            'title' => 'Pendaftaran disetujui',
            'body' => 'Pendaftaran :name telah disetujui. Silakan bayar biaya lalu unggah bukti pembayaran.',
        ],
        'payment_submitted' => [
            'title' => 'Bukti bayar baru',
            'body' => ':name mengunggah bukti :type.',
        ],
        'payment_rejected' => [
            'title' => 'Bukti bayar belum diterima',
            'body' => 'Bukti :type untuk :name belum diterima: :note',
        ],
        'loa_issued' => [
            'title' => 'LOA sudah terbit',
            'body' => 'Letter of Acceptance untuk :name sudah bisa diunduh.',
        ],
        'mou_submitted' => [
            'title' => 'MOU baru untuk diperiksa',
            'body' => ':agency mengunggah MOU.',
        ],
        'mou_approved' => [
            'title' => 'MOU Anda disetujui',
            'body' => 'Sekarang Anda bisa mendaftarkan mahasiswa di SIPMA.',
        ],
        'mou_rejected' => [
            'title' => 'MOU Anda perlu diperbaiki',
            'body' => 'Silakan unggah MOU yang sudah diperbaiki. Alasan: :note',
        ],
    ],

    'timeline_label' => 'Progres pendaftaran',

    'timeline' => [
        'draft' => ['label' => 'Isi pendaftaran', 'description' => 'Data diri dan dokumen'],
        'submitted' => ['label' => 'Diajukan', 'description' => 'Menunggu Kantor Urusan Internasional'],
        'review' => ['label' => 'Verifikasi', 'description' => 'Dokumen sedang diperiksa'],
        'revision' => ['label' => 'Perlu perbaikan', 'description' => 'Perbaiki dokumen yang ditandai lalu ajukan lagi'],
        'payment' => ['label' => 'Pembayaran', 'description' => 'Bayar biaya dan unggah buktinya'],
        'loa' => ['label' => 'Letter of Acceptance', 'description' => 'Siap diunduh'],
    ],

];
