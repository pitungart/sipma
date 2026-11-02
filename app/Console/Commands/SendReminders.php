<?php

namespace App\Console\Commands;

use App\Enums\PaymentStatus;
use App\Enums\StudentCardStatus;
use App\Enums\StudentStatus;
use App\Enums\UserRole;
use App\Models\SentReminder;
use App\Models\Student;
use App\Models\StudentCard;
use App\Models\User;
use App\Notifications\PaymentReminder;
use App\Notifications\StudentCardExpiring;
use App\Notifications\StudentCardsExpiring;
use App\Notifications\SubmissionReminder;
use App\Support\ApplicantDetails;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * §5 Penjadwal: pengingat otomatis (lonceng + email bila SIPMA_MAIL_NOTIFICATIONS aktif).
 * - Belum diajukan (draf / perlu revisi) dan tidak diubah sekian hari (SIPMA_SUBMISSION_REMINDER_AFTER),
 *   sekali per masa diam: diubah lalu diam lagi → diingatkan lagi.
 * - Belum bayar setelah disetujui, berjeda SIPMA_PAYMENT_REMINDER_EVERY hari (VA Unud 24 jam).
 * - KTM mahasiswa Aktif yang habis dalam SIPMA_CARD_EXPIRY_REMINDER_BEFORE hari, sekali per KTM:
 *   ke mahasiswanya dan ringkasan ke Super Admin.
 * Agen menerima satu ringkasan per jenis, mahasiswa mandiri satu per pendaftaran. Setiap kiriman
 * dicatat di sent_reminders sehingga perintah aman dijalankan ulang. Dijalankan tanpa pengguna
 * yang masuk, jadi tercatat sebagai "Sistem".
 */
class SendReminders extends Command
{
    protected $signature = 'sipma:reminders {--dry-run : Tampilkan siapa yang akan dikirimi tanpa mengirim}';

    protected $description = 'Kirim pengingat pendaftaran yang belum diajukan dan lama tidak diubah, biaya yang belum dibayar, serta KTM yang segera habis';

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
        $this->cardExpiry();

        if ($this->rows === []) {
            $this->info('Tidak ada pengingat yang perlu dikirim hari ini.');

            return self::SUCCESS;
        }

        $this->table(['Jenis', 'Penerima', 'Mahasiswa', 'Keterangan'], $this->rows);
        $this->info(($this->option('dry-run') ? '[dry-run] Akan dikirim: ' : 'Terkirim: ').count($this->rows).' pengingat.');

        return self::SUCCESS;
    }

    /**
     * Draf / perlu revisi yang tidak diubah sedikitnya N hari. Perubahan terakhir = data pendaftar
     * atau dokumennya (unggah dokumen tidak menyentuh students.updated_at). Kunci memuat tanggal
     * perubahan terakhir, jadi satu masa diam hanya diingatkan sekali.
     */
    private function submissions(): void
    {
        $days = (int) config('sipma.reminders.submission_after');
        $cutoff = today()->subDays($days);

        $idle = Student::query()
            ->whereIn('status', [StudentStatus::Draft, StudentStatus::Revision])
            ->withMax('documents', 'updated_at')
            ->with(['user', 'agent.user'])
            ->orderBy('full_name')
            ->get()
            ->filter(fn (Student $student): bool => $this->lastChanged($student)->copy()->startOfDay()->lte($cutoff));

        $this->byOwner($idle)->each(function (Collection $group, string $userId) use ($days): void {
            $keys = $group->mapWithKeys(fn (Student $student): array => [
                $student->getKey() => "{$userId}:{$student->getKey()}:{$this->lastChanged($student)->toDateString()}",
            ]);
            $due = $group->filter(fn (Student $student): bool => ! SentReminder::lastSent(SentReminder::SUBMISSION, $keys[$student->getKey()]))->values();

            if ($due->isEmpty()) {
                return;
            }

            $owner = $due->first()->owner();
            $this->deliver($owner, new SubmissionReminder($due, $days), SentReminder::SUBMISSION, $due->map(fn (Student $s): string => $keys[$s->getKey()])->all());
            $this->rows[] = ['Belum diajukan', $owner->email, $due->count(), $due->pluck('full_name')->implode(', ')." · {$days}+ hari"];
        });
    }

    private function lastChanged(Student $student): Carbon
    {
        $document = $student->documents_max_updated_at ? Carbon::parse($student->documents_max_updated_at) : null;

        return $document?->greaterThan($student->updated_at) ? $document : $student->updated_at;
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
     * KTM mahasiswa Aktif yang segera habis. KTM yang sejak terbit sudah dalam jendela pengingat
     * dilewati, agar mahasiswa tidak diingatkan sehari setelah kartunya terbit.
     */
    private function cardExpiry(): void
    {
        $days = (int) config('sipma.reminders.card_expiry_before');

        $cards = StudentCard::query()
            ->where('status', StudentCardStatus::Active)
            ->whereDate('valid_until', '>=', today())
            ->whereDate('valid_until', '<=', today()->addDays($days))
            ->whereHas('student', fn (Builder $student) => $student->where('status', StudentStatus::Enrolled))
            ->with('student.user')
            ->orderBy('valid_until')
            ->get()
            ->filter(fn (StudentCard $card): bool => $card->issued_at->diffInDays($card->valid_until) > $days)
            ->values();

        foreach ($cards as $card) {
            $owner = $card->student->user;
            $key = (string) $card->getKey();

            if ($owner === null || SentReminder::lastSent(SentReminder::CARD_EXPIRY, $key)) {
                continue;
            }

            $this->deliver($owner, new StudentCardExpiring($card), SentReminder::CARD_EXPIRY, [$key]);
            $this->rows[] = ['KTM segera habis', $owner->email, 1, "{$card->student->full_name} · {$card->valid_until->toDateString()}"];
        }

        User::query()->where('role', UserRole::SuperAdmin)->where('is_active', true)->get()->each(function (User $admin) use ($cards): void {
            $key = fn (StudentCard $card): string => "{$admin->getKey()}:{$card->getKey()}";
            $due = $cards->reject(fn (StudentCard $card): bool => (bool) SentReminder::lastSent(SentReminder::CARD_EXPIRY, $key($card)))->values();

            if ($due->isEmpty()) {
                return;
            }

            $this->deliver($admin, new StudentCardsExpiring($due), SentReminder::CARD_EXPIRY, $due->map($key)->all());
            $this->rows[] = ['KTM segera habis', $admin->email, $due->count(), $due->map(fn (StudentCard $card): string => $card->student->full_name)->implode(', ')];
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
    private function deliver(User $owner, SubmissionReminder|PaymentReminder|StudentCardExpiring|StudentCardsExpiring $notification, string $kind, array $keys): void
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
