<?php

// Label nilai Enum yang tampil di formulir & tabel.
return [

    'gender' => [
        'male' => 'Laki-laki',
        'female' => 'Perempuan',
        'other' => 'Lainnya',
    ],

    'role' => [
        'super_admin' => 'Kantor Urusan Internasional',
        'admin' => 'Admin fakultas',
        'agent' => 'Agen mitra',
        'student' => 'Mahasiswa',
    ],

    'payment_type' => [
        'admission_fee' => 'Biaya pendaftaran',
        'tuition_fee' => 'Biaya kuliah',
    ],

    'religion' => [
        'islam' => 'Islam',
        'protestant' => 'Kristen Protestan',
        'catholic' => 'Katolik',
        'hindu' => 'Hindu',
        'buddhist' => 'Buddha',
        'confucian' => 'Konghucu',
        'other' => 'Lainnya',
    ],

    'student_status' => [
        'draft' => 'Draf',
        'submitted' => 'Diajukan',
        'in_review' => 'Sedang diverifikasi',
        'revision' => 'Perlu revisi',
        'approved' => 'Disetujui, menunggu pembayaran',
        'loa_issued' => 'LOA terbit',
        'enrolled' => 'Aktif',
        'completed' => 'Selesai',
    ],

    'document_status' => [
        'pending' => 'Menunggu diperiksa',
        'approved' => 'Disetujui',
        'revision' => 'Perlu revisi',
        'rejected' => 'Ditolak',
    ],

    'document_type' => [
        'photo' => 'Foto (3×4)',
        'guarantor_financial' => 'Pernyataan keuangan penjamin',
        'student_financial' => 'Pernyataan keuangan mahasiswa',
        'declaration' => 'Surat pernyataan',
        'medical_statement' => 'Surat keterangan sehat',
        'passport' => 'Paspor',
        'transcript' => 'Transkrip akademik',
        'recommendation' => 'Surat rekomendasi',
        'other' => 'Dokumen lain',
    ],

    'payment_status' => [
        'pending' => 'Menunggu verifikasi',
        'verified' => 'Terverifikasi',
        'rejected' => 'Ditolak',
    ],

    'loa_status' => [
        'pending' => 'Belum terbit',
        'uploaded' => 'Tersedia',
        'downloaded' => 'Sudah diunduh',
    ],

    'mou_status' => [
        'pending' => 'Menunggu verifikasi',
        'approved' => 'Disetujui',
        'rejected' => 'Ditolak',
    ],

    'visa_status' => [
        'not_started' => 'Belum dimulai',
        'in_process' => 'Sedang diproses',
        'approved' => 'Disetujui',
        'rejected' => 'Ditolak',
    ],

    'grade' => [
        'pass' => 'Lulus',
        'fail' => 'Tidak lulus',
    ],

    'teaching_role' => [
        'coordinator' => 'Koordinator',
        'lecturer' => 'Pengajar',
        'assistant' => 'Asisten',
    ],

    'decree_period' => [
        'upcoming' => 'Akan datang',
        'current' => 'Berlaku',
        'ended' => 'Berakhir',
    ],

    'student_course_status' => [
        'active' => 'Sedang ditempuh',
        'completed' => 'Selesai',
        'dropped' => 'Di-drop',
    ],

    'student_card_status' => [
        'active' => 'Aktif',
        'expired' => 'Kedaluwarsa',
        'revoked' => 'Dicabut',
    ],

];
