<?php

namespace App\Support;

use App\Enums\DocumentStatus;
use App\Enums\PaymentStatus;
use App\Enums\StudentStatus;
use App\Filament\Agent\Resources\StudentResource;
use App\Filament\Support\InitialsAvatarProvider;
use App\Models\AcademicPeriod;
use App\Models\Agent;
use App\Models\Student;
use App\Workflow\SubmissionChecklist;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Isi dasbor kerja agen setelah MOU disetujui (§3 Dashboard agen): kartu angka per tahap,
 * daftar "perlu tindakan", dan program MOU beserta periode yang sedang dibuka.
 */
final class AgentDashboard
{
    /**
     * Baris "perlu tindakan" yang ditampilkan; sisanya dijangkau lewat Mahasiswa saya.
     */
    public const ACTION_LIMIT = 8;

    public function __construct(private readonly Agent $agent) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function cards(): array
    {
        $counts = $this->students()->toBase()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');
        $count = fn (StudentStatus ...$statuses): int => collect($statuses)->sum(fn (StudentStatus $s): int => (int) ($counts[$s->value] ?? 0));

        $ready = $this->readyDrafts()->count();
        $inReview = $count(StudentStatus::InReview);
        $flagged = $this->students()->where('status', StudentStatus::Revision)
            ->withCount(['documents' => fn (Builder $q) => $q->whereIn('status', [DocumentStatus::Revision, DocumentStatus::Rejected])])
            ->get()->sum('documents_count');
        $unpaid = $this->unpaid()->count();
        $notDownloaded = $this->students()->where('status', StudentStatus::LoaIssued)
            ->whereHas('loa', fn (Builder $q) => $q->whereNull('downloaded_at'))->count();

        return [
            $this->card('draft', $count(StudentStatus::Draft), 'lucide-square-pen', 'gray',
                __('agent.home.cards.ready', ['count' => $ready]), $ready > 0 ? 'success' : 'gray'),
            $this->card('submitted', $count(StudentStatus::Submitted, StudentStatus::InReview), 'lucide-send', 'info',
                __('agent.home.cards.in_review', ['count' => $inReview]), 'primary'),
            $this->card('revision', $count(StudentStatus::Revision), 'lucide-triangle-alert', 'danger',
                __('agent.home.cards.flagged', ['count' => $flagged]), 'danger'),
            $this->card('approved', $count(StudentStatus::Approved), 'lucide-wallet', 'warning',
                __('agent.home.cards.unpaid', ['count' => $unpaid]), $unpaid > 0 ? 'warning' : 'gray'),
            $this->card('loa_issued', $count(StudentStatus::LoaIssued), 'lucide-file-check', 'success',
                __('agent.home.cards.not_downloaded', ['count' => $notDownloaded]), $notDownloaded > 0 ? 'success' : 'gray'),
        ];
    }

    /**
     * Mahasiswa yang menunggu tindakan agen, urut dari yang paling mendesak: revisi, pembayaran,
     * draf siap diajukan, lalu LOA yang belum diunduh.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function actions(): Collection
    {
        $revision = $this->students()->where('status', StudentStatus::Revision)->with('program')->latest('updated_at')->get()
            ->map(fn (Student $s): array => $this->action($s, 'danger', 'lucide-triangle-alert',
                filled($s->revision_note) ? $s->revision_note : __('agent.home.actions.revision'), __('agent.home.actions.fix')));

        $unpaid = $this->unpaid()
            ->map(fn (array $item): array => $this->action($item['student'], 'warning', 'lucide-wallet',
                $item['rejected']
                    ? __('agent.home.actions.payment_rejected', ['fees' => $item['fees']])
                    : __('agent.home.actions.unpaid', ['fees' => $item['fees']]),
                __('agent.home.actions.pay')));

        $ready = $this->readyDrafts()
            ->map(fn (Student $s): array => $this->action($s, 'info', 'lucide-send', __('agent.home.actions.ready'), __('agent.home.actions.submit')));

        $loa = $this->students()->where('status', StudentStatus::LoaIssued)
            ->whereHas('loa', fn (Builder $q) => $q->whereNull('downloaded_at'))
            ->with('program')->latest('updated_at')->get()
            ->map(fn (Student $s): array => $this->action($s, 'success', 'lucide-file-check', __('agent.home.actions.loa'), __('agent.home.actions.download')));

        return $revision->concat($unpaid)->concat($ready)->concat($loa)->values();
    }

    /**
     * Program yang dicakup MOU, dengan periode pendaftaran yang sedang dibuka (bila ada).
     *
     * @return Collection<int, array{name: string, period: ?AcademicPeriod}>
     */
    public function programs(): Collection
    {
        return $this->agent->allowedPrograms()->get()->map(fn ($program): array => [
            'name' => $program->name,
            'period' => AcademicPeriod::currentFor($program->getKey()),
        ]);
    }

    /**
     * @return Builder<Student>
     */
    private function students(): Builder
    {
        return Student::query()->where('agent_id', $this->agent->getKey());
    }

    /**
     * Draf yang semua butirnya sudah lengkap — tinggal diajukan.
     *
     * @return Collection<int, Student>
     */
    private function readyDrafts(): Collection
    {
        return $this->students()->where('status', StudentStatus::Draft)->with('program')->latest('updated_at')->get()
            ->filter(fn (Student $s): bool => SubmissionChecklist::for($s)->isComplete())
            ->values();
    }

    /**
     * Mahasiswa disetujui yang masih punya biaya tanpa bukti, atau buktinya ditolak.
     *
     * @return Collection<int, array{student: Student, fees: string, rejected: bool}>
     */
    private function unpaid(): Collection
    {
        return $this->students()->where('status', StudentStatus::Approved)->with('program')->latest('updated_at')->get()
            ->map(function (Student $student): ?array {
                $open = ApplicantDetails::fees($student)
                    ->filter(fn (array $fee): bool => in_array($fee['status'], [null, PaymentStatus::Rejected], true));

                return $open->isEmpty() ? null : [
                    'student' => $student,
                    'fees' => $open->pluck('label')->implode(', '),
                    'rejected' => $open->contains(fn (array $fee): bool => $fee['status'] === PaymentStatus::Rejected),
                ];
            })
            ->filter()
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function card(string $tab, int $value, string $icon, string $tone, string $badge, string $badgeTone): array
    {
        return [
            'label' => __("agent.home.cards.labels.{$tab}"),
            'url' => StudentResource::getUrl('index', ['activeTab' => $tab]),
            'value' => $value,
            'icon' => $icon,
            'tone' => $tone,
            'badge' => $badge,
            'badgeTone' => $badgeTone,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function action(Student $student, string $tone, string $icon, string $reason, string $cta): array
    {
        return [
            'name' => $student->full_name,
            'initials' => InitialsAvatarProvider::initialsOf($student->full_name),
            'avatarTone' => InitialsAvatarProvider::toneOf($student->full_name),
            'program' => $student->program?->name,
            'reason' => $reason,
            'tone' => $tone,
            'icon' => $icon,
            'cta' => $cta,
            'url' => StudentResource::getUrl('view', ['record' => $student]),
        ];
    }
}
