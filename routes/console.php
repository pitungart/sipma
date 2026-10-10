<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// §5 Pengingat otomatis (config/sipma.php → reminders). Server: cron `* * * * * php artisan schedule:run`;
// lokal: `php artisan schedule:work`. SIPMA_REMINDERS=false mematikannya tanpa mengubah jadwal.
Schedule::command('sipma:reminders')
    ->dailyAt(config('sipma.reminders.time'))
    ->withoutOverlapping()
    ->onOneServer();
