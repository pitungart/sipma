<?php

namespace App\Support;

use App\Enums\PaymentStatus;
use App\Enums\StudentStatus;
use App\Models\Student;
use App\Workflow\SubmissionChecklist;
use Illuminate\Support\Collection;

/**
 * Isi dasbor mahasiswa mandiri (/app): tahap sekarang dan kartu "langkah berikutnya"
 * — mulai daftar → lengkapi → ajukan → menunggu KUI → perbaiki → bayar → unduh LOA.
 */
final class StudentHome
{
    public const START = 'start';

    public const COMPLETE = 'complete';

    public const SUBMIT = 'submit';

    public const WAITING = 'waiting';

    public const REVISION = 'revision';

    public const PAY = 'pay';

    public const PAYMENT_REVIEW = 'payment_review';

    public const LOA = 'loa';

    /** @var Collection<int, array<string, mixed>>|null */
    private ?Collection $fees = null;

    public function __construct(public readonly ?Student $application) {}

    public function stage(): string
    {
        $student = $this->application;

        return match (true) {
            $student === null => self::START,
            $student->status === StudentStatus::LoaIssued => self::LOA,
            $student->status === StudentStatus::Revision => self::REVISION,
            $student->status === StudentStatus::Draft => $this->checklist()->isComplete() ? self::SUBMIT : self::COMPLETE,
            $student->status === StudentStatus::Approved => $this->unpaid()->isNotEmpty() ? self::PAY : self::PAYMENT_REVIEW,
            default => self::WAITING, // diajukan / sedang diverifikasi
        };
    }

    /**
     * @return array{title: string, body: string, tone: string, icon: string}
     */
    public function next(): array
    {
        $stage = $this->stage();
        $missing = $this->application && $this->application->status->isEditable() ? $this->checklist()->missing()->count() : 0;

        return [
            'title' => __("student.home.stages.{$stage}.title"),
            'body' => match ($stage) {
                self::REVISION => filled($this->application->revision_note)
                    ? $this->application->revision_note
                    : __('student.home.stages.revision.body'),
                self::PAY => __('student.home.stages.pay.body', ['fees' => $this->unpaid()->pluck('label')->implode(', ')]),
                default => __("student.home.stages.{$stage}.body", ['count' => $missing]),
            },
            'tone' => match ($stage) {
                self::REVISION => 'danger',
                self::PAY, self::COMPLETE => 'warning',
                self::WAITING, self::PAYMENT_REVIEW => 'info',
                self::LOA => 'success',
                default => 'primary',
            },
            'icon' => match ($stage) {
                self::START => 'lucide-rocket',
                self::COMPLETE => 'lucide-square-pen',
                self::SUBMIT => 'lucide-send',
                self::REVISION => 'lucide-triangle-alert',
                self::PAY => 'lucide-wallet',
                self::LOA => 'lucide-file-check',
                default => 'lucide-clock',
            },
        ];
    }

    public function checklist(): SubmissionChecklist
    {
        return SubmissionChecklist::for($this->application);
    }

    /**
     * Biaya yang belum ada buktinya atau buktinya ditolak.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function unpaid(): Collection
    {
        return $this->fees()->filter(fn (array $fee): bool => in_array($fee['status'], [null, PaymentStatus::Rejected], true))->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function fees(): Collection
    {
        return $this->fees ??= $this->application ? ApplicantDetails::fees($this->application) : new Collection;
    }
}
