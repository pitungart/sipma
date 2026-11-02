<?php

// Teks alur pendaftaran (StudentWorkflow, MouWorkflow, checklist, notifikasi, garis waktu).
return [

    'documents' => [
        'format' => ':types · maks. :max',
        'photo_rules' => 'Foto formal 3×4 berlatar biru.',
        'template' => 'Template',
        'template_tooltip' => 'Unduh template :name',
        'template_link' => 'Unduh template dokumen ini.',
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
        'enroll' => 'menandai mahasiswa aktif',
        'complete' => 'menandai program selesai',
        'reopen' => 'membuka kembali status aktif',
        'issue_card' => 'menerbitkan KTM',
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
        'fees_not_set' => 'Belum bisa disetujui: :types program :program masih Rp 0. Tetapkan biayanya di Data master → Program.',
        'va_not_set' => 'Belum bisa disetujui: rekening VA untuk :types belum disiapkan. Tambahkan di Data master → Rekening pembayaran.',
        'payment_not_pending' => 'Pembayaran ini sudah diproses.',
        'payments_outstanding' => 'LOA bisa diterbitkan setelah pembayaran berikut terverifikasi: :types.',
        'mou_already_approved' => 'MOU Anda sudah disetujui.',
        'mou_already_pending' => 'MOU Anda masih menunggu diperiksa.',
        'mou_not_pending' => 'MOU ini sudah diproses.',
        'profile_incomplete' => 'Lengkapi profil perusahaan sebelum mengunggah MOU.',
        'mou_programs_required' => 'Pilih minimal satu program yang dicakup MOU ini.',
        'card_already_active' => ':name masih punya KTM aktif. Gunakan Terbitkan ulang untuk menggantinya.',
        'card_not_active' => 'KTM :number sudah tidak aktif.',
        'card_photo_missing' => 'KTM belum bisa diterbitkan: Pas Foto belum ada atau belum disetujui.',
        'card_valid_until_required' => 'Isi tanggal berlaku KTM. Tanggal tidak bisa dihitung karena durasi program atau periode masuk masih kosong.',
        'card_valid_until_past' => 'Tanggal berlaku KTM harus setelah hari ini.',
        'card_number_taken' => 'Nomor KTM :number sudah dipakai.',
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
        'student_enrolled' => [
            'title' => 'Terdaftar sebagai mahasiswa aktif',
            'body' => ':name sudah terdaftar aktif di program :program. KTM dan rubrik akademik akan tersedia di portal.',
        ],
        'card_issued' => [
            'title' => 'KTM Anda sudah terbit',
            'body' => 'KTM nomor :number sudah terbit dan berlaku sampai :date.',
        ],
        'card_reissued' => [
            'title' => 'KTM Anda diterbitkan ulang',
            'body' => 'KTM baru nomor :number berlaku sampai :date dan menggantikan KTM lama. Alasan: :reason',
        ],
        'card_revoked' => [
            'title' => 'KTM Anda dicabut',
            'body' => 'KTM nomor :number sudah tidak berlaku. Alasan: :reason',
        ],
        'student_invitation' => [
            'title' => 'Akun portal SIPMA Anda',
            'body' => 'Selamat, :name sudah terdaftar aktif di program :program. Buat password untuk masuk ke portal SIPMA dan melihat KTM serta rubrik akademik Anda.',
            'action' => 'Buat password',
            'expiry' => 'Tautan ini berlaku :days hari dan hanya bisa dipakai sekali. Setelah itu, masuk dengan email :email.',
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
        'and_more' => 'dan :count lainnya',
        'submission_reminder' => [
            'title' => '{1} Pendaftaran belum diajukan|[2,*] Pendaftaran mahasiswa belum diajukan',
            'body_one' => 'Pendaftaran :names belum diajukan dan tidak diubah selama :days hari. Lengkapi lalu ajukan agar KUI bisa segera memeriksanya.',
            'body_many' => ':count mahasiswa belum diajukan dan tidak diubah selama :days hari: :names. Lengkapi lalu ajukan agar KUI bisa segera memeriksanya.',
        ],
        'card_expiring' => [
            'title' => 'KTM Anda segera habis',
            'body' => 'KTM nomor :number berlaku sampai :date. Hubungi KUI bila Anda masih memerlukannya setelah tanggal itu.',
        ],
        'cards_expiring' => [
            'title' => 'KTM segera habis',
            'body' => ':count KTM mahasiswa aktif segera habis: :names. Terbitkan ulang bila programnya masih berjalan.',
        ],
        'payment_reminder' => [
            'title' => 'Biaya belum dibayar',
            'body_one' => 'Pendaftaran :name sudah disetujui, tetapi :fees belum dibayar. Nomor VA berlaku 24 jam — segera bayar lalu unggah buktinya.',
            'body_many' => ':count mahasiswa sudah disetujui tetapi belum membayar: :names. Nomor VA berlaku 24 jam.',
        ],
    ],

    'timeline_label' => 'Progres pendaftaran',

    'accounts' => [
        'existing' => 'Mahasiswa sudah punya akun portal.',
        'created' => 'Akun portal dibuat dan undangan dikirim ke email mahasiswa.',
        'linked' => 'Dihubungkan ke akun portal yang sudah terdaftar dengan email ini.',
        'missing_email' => 'Akun portal belum dibuat: email mahasiswa kosong.',
        'email_taken' => 'Akun portal belum dibuat: email mahasiswa sudah dipakai akun lain.',
    ],

    'timeline' => [
        'draft' => ['label' => 'Isi pendaftaran', 'description' => 'Data diri dan dokumen'],
        'submitted' => ['label' => 'Diajukan', 'description' => 'Menunggu Kantor Urusan Internasional'],
        'review' => ['label' => 'Verifikasi', 'description' => 'Dokumen sedang diperiksa'],
        'revision' => ['label' => 'Perlu perbaikan', 'description' => 'Perbaiki dokumen yang ditandai lalu ajukan lagi'],
        'payment' => ['label' => 'Pembayaran', 'description' => 'Bayar biaya dan unggah buktinya'],
        'loa' => ['label' => 'Letter of Acceptance', 'description' => 'Siap diunduh'],
    ],

];
