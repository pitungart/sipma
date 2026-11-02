<?php

namespace App\Console\Commands;

use App\Models\StudentCard;
use App\Workflow\StudentCardWorkflow;
use Illuminate\Console\Command;

/**
 * KTM aktif yang tanggal berlakunya sudah lewat menjadi Kedaluwarsa. Terpisah dari sipma:reminders
 * agar status tetap benar walau pengingat dimatikan (SIPMA_REMINDERS=false). Dijalankan tanpa
 * pengguna yang masuk, jadi tercatat sebagai "Sistem". Aman dijalankan ulang.
 */
class ExpireStudentCards extends Command
{
    protected $signature = 'sipma:expire-cards {--dry-run : Tampilkan KTM yang akan kedaluwarsa tanpa mengubahnya}';

    protected $description = 'Tandai KTM yang sudah lewat masa berlakunya sebagai kedaluwarsa';

    public function handle(StudentCardWorkflow $workflow): int
    {
        $due = StudentCard::query()->overdue();

        if ($this->option('dry-run')) {
            $this->info('[dry-run] Akan kedaluwarsa: '.$due->count().' KTM.');

            return self::SUCCESS;
        }

        $expired = 0;

        $due->lazyById()->each(function (StudentCard $card) use ($workflow, &$expired): void {
            $expired += $workflow->expire($card) ? 1 : 0;
        });

        $this->info("Kedaluwarsa: {$expired} KTM.");

        return self::SUCCESS;
    }
}
