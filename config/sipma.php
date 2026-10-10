<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Lewati verifikasi email saat daftar
    |--------------------------------------------------------------------------
    |
    | Untuk pengembangan lokal: akun yang mendaftar di /register langsung ditandai
    | terverifikasi, sehingga email verifikasi tidak dikirim. Bawaannya mengikuti
    | APP_DEBUG; bisa ditimpa dengan SIPMA_SKIP_EMAIL_VERIFICATION. Tidak pernah
    | berlaku di production (lihat App\Livewire\Auth\Register).
    |
    */

    'skip_email_verification' => (bool) env('SIPMA_SKIP_EMAIL_VERIFICATION', env('APP_DEBUG', false)),

    /*
    |--------------------------------------------------------------------------
    | Email notifikasi alur
    |--------------------------------------------------------------------------
    |
    | Notifikasi alur (pendaftaran, revisi, pembayaran, LOA, MOU) selalu masuk lonceng
    | panel; email hanya dikirim bila ini aktif. Bawaannya mati saat APP_DEBUG=true agar
    | pengembangan lokal tidak mengirim email; timpa dengan SIPMA_MAIL_NOTIFICATIONS.
    | Panduan Gmail SMTP: agents/panduan-email-gmail-smtp.md.
    |
    */

    'mail_notifications' => (bool) env('SIPMA_MAIL_NOTIFICATIONS', ! env('APP_DEBUG', false)),

    /*
    |--------------------------------------------------------------------------
    | Email lewat antrean
    |--------------------------------------------------------------------------
    |
    | true: email notifikasi dikirim lewat antrean (QUEUE_CONNECTION, perlu `queue:work`),
    | sehingga SMTP yang gagal tidak menggagalkan aksi di panel. Lonceng tetap langsung.
    | false (bawaan): email dikirim saat itu juga, seperti sebelumnya.
    |
    */

    'queue_mail' => (bool) env('SIPMA_QUEUE_MAIL', false),

    /*
    |--------------------------------------------------------------------------
    | Pengingat terjadwal (php artisan sipma:reminders)
    |--------------------------------------------------------------------------
    |
    | Dijalankan penjadwal Laravel setiap hari pada `time` (zona APP_TIMEZONE); server perlu
    | cron `* * * * * php artisan schedule:run`, lokal cukup `php artisan schedule:work`.
    | - enabled: saklar utama; false = perintah berhenti tanpa mengirim apa pun.
    | - submission_days: H-sekian sebelum periode ditutup untuk draf / perlu revisi.
    | - payment_every: jeda hari pengingat "belum bayar" (VA Unud berlaku 24 jam → 1 hari).
    |
    */

    'reminders' => [
        'enabled' => (bool) env('SIPMA_REMINDERS', true),
        'submission_days' => array_values(array_filter(array_map('intval', explode(',', (string) env('SIPMA_REMINDER_DAYS', '7,1'))), fn (int $d): bool => $d > 0)),
        'payment_every' => max(1, (int) env('SIPMA_PAYMENT_REMINDER_EVERY', 1)),
        'time' => (string) env('SIPMA_REMINDER_TIME', '08:00'),
    ],

];
