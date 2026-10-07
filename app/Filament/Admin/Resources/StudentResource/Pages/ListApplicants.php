<?php

namespace App\Filament\Admin\Resources\StudentResource\Pages;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\StudentStatus;
use App\Filament\Admin\Resources\Pages\ListWithStatusTabs;
use App\Filament\Admin\Resources\StudentResource;
use App\Filament\Support\FormModal;
use App\Filament\Support\InitialsAvatarProvider;
use App\Models\Student;
use App\Support\ApplicantExport;
use App\Workflow\StudentWorkflow;
use App\Workflow\WorkflowException;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Components\Tab;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Daftar pendaftar dalam dua tampilan (keputusan 6 Oktober 2026):
 * - **Tabel** dengan tab status (Semua · Diajukan · … · Draf); ?activeTab= dipakai kartu dashboard.
 * - **Kanban** lima kolom alur (tanpa draf). Kartu bisa diseret hanya untuk transisi yang sah,
 *   dan setiap kartu punya tombol langkah berikutnya sebagai pengganti seret (keyboard).
 */
class ListApplicants extends ListWithStatusTabs
{
    protected static string $resource = StudentResource::class;

    protected static string $view = 'filament.admin.applicants.list';

    /**
     * Urutan tab mengikuti alur kerja KUI, draf paling akhir karena belum perlu tindakan.
     */
    private const TAB_ORDER = [
        StudentStatus::Submitted,
        StudentStatus::InReview,
        StudentStatus::Revision,
        StudentStatus::Approved,
        StudentStatus::LoaIssued,
        StudentStatus::Draft,
    ];

    /**
     * Kolom kanban: alur yang melibatkan KUI, tanpa draf.
     */
    public const BOARD_COLUMNS = [
        StudentStatus::Submitted,
        StudentStatus::InReview,
        StudentStatus::Revision,
        StudentStatus::Approved,
        StudentStatus::LoaIssued,
    ];

    /**
     * Kartu per kolom yang dirender; sisanya dijangkau lewat tampilan tabel.
     */
    public const BOARD_LIMIT = 30;

    #[Url(as: 'view')]
    public string $display = 'table';

    public function getSubheading(): ?string
    {
        return __('admin.applicant.description');
    }

    public function isBoard(): bool
    {
        return $this->display === 'kanban';
    }

    public function getTabs(): array
    {
        $counts = Student::query()
            ->visibleTo(Filament::auth()->user())
            ->toBase()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'all' => Tab::make(__('admin.applicant.tabs.all'))->badge($counts->sum()),
            ...collect(self::TAB_ORDER)->mapWithKeys(fn (StudentStatus $status): array => [
                $status->value => Tab::make($status->getLabel())
                    ->badge((int) ($counts[$status->value] ?? 0))
                    ->badgeColor($status->getColor())
                    ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', $status)),
            ])->all(),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            // Sakelar tampilan sebagai satu segmen (template); isinya Blade biasa dengan $set Livewire
            Action::make('display')->view('filament.admin.applicants.display-toggle'),

            Action::make('export')
                ->label(__('admin.applicant.export'))
                ->icon('lucide-download')
                ->color('gray')
                ->visible(fn (): bool => ! $this->isBoard())
                // Ekspor = yang sedang dilihat: tab, pencarian, dan saringan aktif
                ->action(fn (): StreamedResponse => ApplicantExport::download($this->getFilteredSortedTableQuery())),

            Action::make('newApplication')
                ->label(__('admin.applicant.actions.new'))
                ->icon('lucide-plus')
                ->visible(fn (): bool => StudentResource::canCreate())
                ->url(fn (): string => StudentResource::getUrl('create')),
        ];
    }

    // ── Kanban ───────────────────────────────────────────────────────────────

    /**
     * Ability `verify` tidak bergantung pada data pendaftar tertentu (Super Admin via Gate::before),
     * jadi diperiksa terhadap instance kosong untuk menentukan apakah kartu boleh diseret.
     */
    public function canMoveCards(): bool
    {
        return Gate::allows('verify', new Student);
    }

    /**
     * @return Collection<int, array{status: StudentStatus, total: int, cards: Collection<int, array<string, mixed>>}>
     */
    public function boardColumns(): Collection
    {
        $required = DocumentType::required();
        $base = fn (): Builder => Student::query()->visibleTo(Filament::auth()->user());

        return collect(self::BOARD_COLUMNS)->map(fn (StudentStatus $status): array => [
            'status' => $status,
            'total' => $base()->where('status', $status)->count(),
            'cards' => $base()
                ->where('status', $status)
                ->with(['program:id,name', 'agent:id,company_name', 'nationality'])
                ->withCount(['documents as approved_documents_count' => fn (Builder $q) => $q
                    ->where('status', DocumentStatus::Approved)
                    ->whereIn('type', $required)])
                ->orderByDesc('submitted_at')
                ->limit(self::BOARD_LIMIT)
                ->get()
                ->map(fn (Student $student): array => $this->card($student, count($required))),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function card(Student $student, int $required): array
    {
        $approved = (int) $student->approved_documents_count;

        // Tombol langkah berikutnya (template): aksi langsung bila tanpa isian, selain itu buka detail.
        $next = match ($student->status) {
            StudentStatus::Submitted => ['label' => __('admin.applicant.kanban.start_review'), 'move' => StudentStatus::InReview->value],
            StudentStatus::InReview => $approved === $required
                ? ['label' => __('admin.applicant.kanban.approve'), 'move' => StudentStatus::Approved->value]
                : ['label' => __('admin.applicant.kanban.check_documents'), 'move' => null],
            StudentStatus::Approved => ['label' => __('admin.applicant.kanban.issue_loa'), 'move' => null],
            default => null,
        };

        return [
            'id' => $student->getKey(),
            'url' => StudentResource::getUrl('view', ['record' => $student]),
            'name' => $student->full_name,
            'initials' => InitialsAvatarProvider::initialsOf($student->full_name),
            'tone' => InitialsAvatarProvider::toneOf($student->full_name),
            'country' => collect([$student->registration_number, $student->nationality?->name])->filter()->implode(' · ') ?: null,
            'program' => $student->program?->name,
            'agent' => $student->agent?->company_name ?? __('admin.dashboard.source_self'),
            'documents' => "{$approved}/{$required}",
            'documentsPercent' => $required === 0 ? 0 : round($approved / $required * 100),
            'documentsTone' => match (true) {
                $approved === $required => 'success',
                $student->status === StudentStatus::Revision => 'danger',
                default => 'warning',
            },
            'next' => $next,
        ];
    }

    /**
     * Seret kartu / tombol langkah berikutnya. Hanya transisi yang punya padanan di
     * StudentWorkflow; selain itu ditolak dengan pesan. Revisi butuh catatan → buka modal.
     */
    public function moveCard(string $studentId, string $to): void
    {
        $student = Student::query()->visibleTo(Filament::auth()->user())->findOrFail($studentId);

        abort_unless(Gate::allows('verify', $student), 403);

        $target = StudentStatus::tryFrom($to);

        if ($target === $student->status) {
            return;
        }

        $step = match ([$student->status, $target]) {
            [StudentStatus::Submitted, StudentStatus::InReview] => fn (StudentWorkflow $w) => $w->startReview($student),
            [StudentStatus::InReview, StudentStatus::Approved] => fn (StudentWorkflow $w) => $w->approve($student),
            [StudentStatus::InReview, StudentStatus::Revision] => 'revision',
            default => null,
        };

        if ($step === 'revision') {
            $this->mountAction('boardRevision', ['student' => $student->getKey()]);

            return;
        }

        if ($step === null) {
            Notification::make()
                ->danger()
                ->title(__('admin.applicant.kanban.illegal_move', [
                    'from' => $student->status->getLabel(),
                    'to' => $target?->getLabel() ?? $to,
                ]))
                ->body(__('admin.applicant.kanban.illegal_move_hint'))
                ->send();

            return;
        }

        $this->runBoardStep($step, $target === StudentStatus::InReview ? 'started_review' : 'approved');
    }

    /**
     * Modal catatan saat kartu diseret ke "Perlu revisi" (sama dengan "Minta revisi" di detail).
     */
    public function boardRevisionAction(): Action
    {
        return FormModal::apply(Action::make('boardRevision'))
            ->modalHeading(fn (array $arguments): string => __('admin.applicant.actions.request_revision').' · '.$this->boardStudent($arguments)->full_name)
            ->modalDescription(__('admin.applicant.revision_modal'))
            ->modalIcon('lucide-undo-2')
            ->modalIconColor('danger')
            ->modalWidth(MaxWidth::Large)
            ->modalSubmitActionLabel(__('admin.applicant.actions.send_revision'))
            ->form([
                Textarea::make('note')
                    ->label(__('admin.applicant.fields.general_note'))
                    ->helperText(__('admin.applicant.kanban.revision_hint'))
                    ->rows(3),
            ])
            ->action(fn (array $arguments, array $data) => $this->runBoardStep(
                fn (StudentWorkflow $w) => $w->requestRevision($this->boardStudent($arguments), $data['note'] ?? null),
                'revision_sent',
            ));
    }

    private function boardStudent(array $arguments): Student
    {
        $student = Student::query()->visibleTo(Filament::auth()->user())->findOrFail($arguments['student'] ?? null);

        abort_unless(Gate::allows('verify', $student), 403);

        return $student;
    }

    private function runBoardStep(\Closure $step, string $successKey): void
    {
        try {
            app()->call($step);
        } catch (WorkflowException $e) {
            Notification::make()->danger()->title($e->getMessage())->send();

            return;
        }

        Notification::make()->success()->title(__("admin.applicant.done.{$successKey}"))->send();
    }
}
