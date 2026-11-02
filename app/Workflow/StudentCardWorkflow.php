<?php

namespace App\Workflow;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\NumberType;
use App\Enums\StudentCardStatus;
use App\Enums\StudentStatus;
use App\Models\Student;
use App\Models\StudentCard;
use App\Notifications\StudentCardIssued;
use App\Notifications\StudentCardRevoked;
use App\Support\Numbering;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Satu-satunya tempat yang menerbitkan dan mengubah KTM (TP-2). Baris mahasiswa dikunci selama
 * transaksi sehingga dua penerbitan bersamaan tidak menghasilkan dua KTM aktif.
 *
 * Seperti StudentWorkflow, siapa yang boleh memanggilnya diperiksa Policy di layar.
 */
final class StudentCardWorkflow
{
    /**
     * Terbitkan KTM untuk mahasiswa Aktif. Masa berlaku bawaan dihitung dari periode masuk dan
     * durasi program; nomor otomatis (Sistem → Penomoran) bila staf tidak mengisinya.
     */
    public function issue(Student $student, ?CarbonInterface $validUntil = null, ?string $number = null): StudentCard
    {
        return DB::transaction(function () use ($student, $validUntil, $number): StudentCard {
            $student = $this->locked($student);

            $this->expectEnrolled($student);

            if ($student->studentCards()->where('status', StudentCardStatus::Active)->exists()) {
                throw WorkflowException::because('card_already_active', ['name' => $student->full_name]);
            }

            $this->expectApprovedPhoto($student);

            $card = $this->createCard($student, $validUntil ?? self::defaultValidUntil($student), $number);

            DB::afterCommit(fn () => $student->user?->notify(new StudentCardIssued($card)));

            return $card;
        });
    }

    /**
     * Ganti KTM aktif dengan yang baru (hilang, rusak, data berubah): nomor baru, masa berlaku
     * bawaan mengikuti KTM lama, dan rantai `replaces_id` menyimpan asal-usulnya.
     */
    public function reissue(StudentCard $card, string $reason, ?CarbonInterface $validUntil = null, ?string $number = null): StudentCard
    {
        return DB::transaction(function () use ($card, $reason, $validUntil, $number): StudentCard {
            $student = $this->locked($card->student);

            $this->expectActive($card);
            $this->expectEnrolled($student);
            $this->expectApprovedPhoto($student);

            $this->markRevoked($card, $reason);

            $replacement = $this->createCard($student, $validUntil ?? $card->valid_until, $number, $card);

            DB::afterCommit(fn () => $student->user?->notify(new StudentCardIssued($replacement, $reason)));

            return $replacement;
        });
    }

    /**
     * Cabut KTM aktif tanpa penggantinya. Berlaku juga untuk mahasiswa yang sudah Selesai.
     */
    public function revoke(StudentCard $card, string $reason): StudentCard
    {
        return DB::transaction(function () use ($card, $reason): StudentCard {
            $student = $this->locked($card->student);

            $this->expectActive($card);
            $this->markRevoked($card, $reason);

            DB::afterCommit(fn () => $student->user?->notify(new StudentCardRevoked($card)));

            return $card;
        });
    }

    /**
     * Akhir bulan terakhir program: masuk November 2026 selama 6 bulan → 30 April 2027.
     * Kosong bila durasi program atau periode masuk belum diisi; KUI mengisi tanggalnya sendiri.
     */
    public static function defaultValidUntil(Student $student): ?Carbon
    {
        $duration = $student->program?->duration_months;

        if (! $duration || $student->enrollment_month === null || $student->enrollment_year === null) {
            return null;
        }

        return Carbon::create($student->enrollment_year, $student->enrollment_month, 1)
            ->addMonthsNoOverflow($duration)
            ->subDay();
    }

    private function createCard(Student $student, ?CarbonInterface $validUntil, ?string $number, ?StudentCard $replaces = null): StudentCard
    {
        if ($validUntil === null) {
            throw WorkflowException::because('card_valid_until_required');
        }

        if (! $validUntil->isAfter(today())) {
            throw WorkflowException::because('card_valid_until_past');
        }

        if (filled($number) && StudentCard::query()->where('card_number', $number)->exists()) {
            throw WorkflowException::because('card_number_taken', ['number' => $number]);
        }

        return $student->studentCards()->create([
            'card_number' => filled($number) ? $number : Numbering::next(NumberType::StudentCard),
            'issued_at' => today(),
            'valid_until' => $validUntil,
            'status' => StudentCardStatus::Active,
            'issued_by' => auth()->id(),
            'replaces_id' => $replaces?->getKey(),
        ]);
    }

    private function markRevoked(StudentCard $card, string $reason): void
    {
        $card->update([
            'status' => StudentCardStatus::Revoked,
            'revoked_at' => now(),
            'revoke_reason' => $reason,
        ]);
    }

    /**
     * Baris terbaru mahasiswa, terkunci sampai transaksi selesai.
     */
    private function locked(Student $student): Student
    {
        return Student::query()->whereKey($student->getKey())->lockForUpdate()->firstOrFail();
    }

    private function expectEnrolled(Student $student): void
    {
        if ($student->status !== StudentStatus::Enrolled) {
            throw WorkflowException::transition($student->status, 'issue_card');
        }
    }

    private function expectActive(StudentCard $card): void
    {
        if (! $card->refresh()->isActive()) {
            throw WorkflowException::because('card_not_active', ['number' => $card->card_number]);
        }
    }

    private function expectApprovedPhoto(Student $student): void
    {
        $approved = $student->documents()
            ->where('type', DocumentType::Photo)
            ->where('status', DocumentStatus::Approved)
            ->exists();

        if (! $approved) {
            throw WorkflowException::because('card_photo_missing');
        }
    }
}
