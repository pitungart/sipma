<?php

namespace App\Console\Commands;

use App\Enums\PaymentStatus;
use App\Enums\StudentStatus;
use App\Models\AcademicPeriod;
use App\Models\SentReminder;
use App\Models\Student;
use App\Models\User;
use App\Notifications\PaymentReminder;
use App\Notifications\SubmissionReminder;
use App\Support\ApplicantDetails;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * §5 Penjadwal: pengingat otomatis (lonceng + email bila SIPMA_MAIL_NOTIFICATIONS aktif).
 * - Belum diajukan (draf / perlu revisi) H-sekian sebelum periode ditutup (SIPMA_REMINDER_DAYS).
 * - Belum bayar setelah disetujui, berjeda SIPMA_PAYMENT_REMINDER_EVERY hari (VA Unud 24 jam).
 * Agen menerima satu ringkasan per jenis, mahasiswa mandiri satu per pendaftaran. Setiap kiriman
 * dicatat di sent_reminders sehingga perintah aman dijalankan ulang. Dijalankan tanpa pengguna
 * yang masuk, jadi tercatat sebagai "Sistem".
 */
class SendReminders extends Command
{
    protected $signature = 'sipma:reminders {--dry-run : Tampilkan siapa yang akan dikirimi tanpa mengirim}';

    protected $description = 'Kirim pengingat pendaftaran yang belum diajukan menjelang periode ditutup dan biaya yang belum dibayar';

    /** @var list<array{string, string, int, string}> */
    private array $rows = [];

    public function handle(): int
    {
        if (! config('sipma.reminders.enabled')) {
            $this->info('Pengingat dimatikan (SIPMA_REMINDERS=false).');

            return self::SUCCESS;
        }

        $this->submissions();
        $this->payments();

        if ($this->rows === []) {
            $this->info('Tidak ada pengingat yang perlu dikirim hari ini.');

            return self::SUCCESS;
        }

        $this->table(['Jenis', 'Penerima', 'Mahasiswa', 'Keterangan'], $this->rows);
        $this->info(($this->option('dry-run') ? '[dry-run] Akan dikirim: ' : 'Terkirim: ').count($this->rows).' pengingat.');

        return self::SUCCESS;
    }

    /**
     * Draf / perlu revisi pada periode yang ditutup tepat H-sekian dari hari ini.
     */
    private function submissions(): void
    {
        foreach (config('sipma.reminders.submission_days') as $days) {
            $periods = AcademicPeriod::query()
                ->where('is_active', true)
                ->whereDate('registration_closes_at', today()->addDays($days))
                ->get();

            foreach ($periods as $period) {
                $students = Student::query()
                    ->whereIn('status', [StudentStatus::Draft, StudentStatus::Revision])
                    ->where(fn ($q) => $q->where('academic_period_id', $period->getKey())
                        // draf lama tanpa periode ikut periode program yang sedang dibuka
                        ->orWhere(fn ($q) => $q->whereNull('academic_period_id')->where('program_id', $period->program_id)))
                    ->with(['user', 'agent.user'])
                    ->orderBy('full_name')
                    ->get();

                $this->byOwner($students)->each(function (Collection $group, string $userId) use ($period, $days): void {
                    $key = "{$userId}:{$period->getKey()}:{$days}";

                    if (SentReminder::lastSent(SentReminder::SUBMISSION, $key)) {
                        return;
                    }

                    $owner = $group->first()->owner();
                    $this->deliver($owner, new SubmissionReminder($group->values(), $period, $days), SentReminder::SUBMISSION, [$key]);
                    $this->rows[] = ['Belum diajukan', $owner->email, $group->count(), "{$period->name} · H-{$days}"];
                });
            }
        }
    }

    /**
     * Disetujui tetapi masih ada biaya tanpa bukti / bukti ditolak; jeda dihitung per mahasiswa
     * sejak pengingat terakhir (atau sejak pendaftaran terakhir berubah, yaitu saat disetujui).
     */
    private function payments(): void
    {
        $every = (int) config('sipma.reminders.payment_every');
        $cutoff = today()->subDays($every);

        $due = Student::query()
            ->where('status', StudentStatus::Approved)
            ->with(['program', 'user', 'agent.user'])
            ->orderBy('full_name')
            ->get()
            ->filter(function (Student $student) use ($cutoff): bool {
                $last = SentReminder::lastSent(SentReminder::PAYMENT, $student->getKey())?->sent_at ?? $student->updated_at;

                return $last->copy()->startOfDay()->lte($cutoff) && $this->unpaid($student)->isNotEmpty();
            });

        $this->byOwner($due)->each(function (Collection $group): void {
            $owner = $group->first()->owner();
            $unpaid = $group->count() === 1 ? $this->unpaid($group->first()) : collect();

            $this->deliver($owner, new PaymentReminder($group->values(), $unpaid->map(fn (array $fee): string => $fee['type']->value)->all()), SentReminder::PAYMENT, $group->map->getKey()->all());
            $this->rows[] = ['Belum bayar', $owner->email, $group->count(), $unpaid->pluck('label')->implode(', ') ?: $group->pluck('full_name')->implode(', ')];
        });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function unpaid(Student $student): Collection
    {
        return ApplicantDetails::fees($student)
            ->filter(fn (array $fee): bool => in_array($fee['status'], [null, PaymentStatus::Rejected], true))
            ->values();
    }

    /**
     * Kelompokkan per akun penerima (mahasiswa mandiri / agen); pendaftar tanpa akun dilewati.
     *
     * @param  Collection<int, Student>  $students
     * @return Collection<string, Collection<int, Student>>
     */
    private function byOwner(Collection $students): Collection
    {
        return $students
            ->filter(fn (Student $student): bool => $student->owner() !== null)
            ->groupBy(fn (Student $student): string => (string) $student->owner()->getKey());
    }

    /**
     * @param  list<string>  $keys
     */
    private function deliver(User $owner, SubmissionReminder|PaymentReminder $notification, string $kind, array $keys): void
    {
        if ($this->option('dry-run')) {
            return;
        }

        $owner->notify($notification);

        foreach ($keys as $key) {
            SentReminder::create(['kind' => $kind, 'key' => $key, 'user_id' => $owner->getKey(), 'sent_at' => now()]);
        }
    }
}
