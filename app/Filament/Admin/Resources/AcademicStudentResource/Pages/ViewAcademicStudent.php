<?php

namespace App\Filament\Admin\Resources\AcademicStudentResource\Pages;

use App\Enums\StudentStatus;
use App\Enums\VisaProcessStatus;
use App\Filament\Admin\Resources\AcademicStudentResource;
use App\Filament\Admin\Resources\StudentResource;
use App\Filament\Support\FormModal;
use App\Filament\Support\StudentCardActions;
use App\Models\Student;
use App\Models\StudentCard;
use App\Models\VisaStatus;
use App\Support\ActivityDescriber;
use App\Support\ApplicantDetails;
use App\Support\EnrollmentPeriod;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Gate;

/**
 * Detail Mahasiswa Asing (TP-1 F2): ringkasan tahap pasca-LOA + tab Info · Visa · KTM · Riwayat.
 * Tab Rubrik dan SK Mengajar ditambahkan TP-3 dan TP-4 lewat tabs().
 *
 * Super Admin melihat dokumen, biaya, dan LOA (seperti detail Pendaftar); Admin Fakultas hanya
 * data ringkas (keputusan 6 Oktober 2026). Visa diubah hanya oleh Super Admin.
 *
 * @property Student $record
 */
class ViewAcademicStudent extends ViewRecord
{
    protected static string $resource = AcademicStudentResource::class;

    protected static string $view = 'filament.admin.academic-students.view';

    public function getTitle(): string|Htmlable
    {
        return $this->record->full_name;
    }

    public function getSubheading(): ?string
    {
        return collect([$this->record->registration_number, $this->record->passport_number, $this->record->nationality?->name])
            ->filter()
            ->implode(' · ');
    }

    /**
     * @return array<int|string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [
            __('admin.groups.academic'),
            AcademicStudentResource::getUrl() => AcademicStudentResource::getPluralModelLabel(),
            $this->record->full_name,
        ];
    }

    /**
     * Data lengkap pendaftaran (dokumen, biaya, LOA) mengikuti ability yang sama dengan detail Pendaftar.
     */
    public function canVerify(): bool
    {
        return Gate::allows('verify', $this->record);
    }

    /**
     * @return array<string, string>
     */
    public function tabs(): array
    {
        return [
            'info' => __('admin.academic_student.tabs.info'),
            'visa' => __('admin.academic_student.tabs.visa'),
            'card' => __('admin.academic_student.tabs.card'),
            'history' => __('admin.academic_student.tabs.history'),
        ];
    }

    protected function getHeaderActions(): array
    {
        $step = fn (string $name) => function () use ($name): void {
            AcademicStudentResource::runStep($this->record, $name);
            $this->record->refresh();
        };

        return [
            Action::make('enroll')
                ->label(__('admin.academic_student.actions.enroll'))
                ->icon('lucide-graduation-cap')
                ->color('success')
                ->visible(fn (): bool => $this->record->status === StudentStatus::LoaIssued && Gate::allows('enroll', $this->record))
                ->requiresConfirmation()
                ->modalIcon('lucide-graduation-cap')
                ->modalIconColor('success')
                ->modalHeading(fn (): string => __('admin.academic_student.confirm.enroll_heading', ['name' => $this->record->full_name]))
                ->modalDescription(fn (): string => AcademicStudentResource::enrollDescription($this->record))
                ->modalSubmitActionLabel(__('admin.academic_student.actions.enroll'))
                ->action($step('enroll')),

            Action::make('complete')
                ->label(__('admin.academic_student.actions.complete'))
                ->icon('lucide-badge-check')
                ->color('gray')
                ->visible(fn (): bool => $this->record->status === StudentStatus::Enrolled && Gate::allows('complete', $this->record))
                ->requiresConfirmation()
                ->modalIcon('lucide-badge-check')
                ->modalHeading(fn (): string => __('admin.academic_student.confirm.complete_heading', ['name' => $this->record->full_name]))
                ->modalDescription(__('admin.academic_student.confirm.complete_body'))
                ->modalSubmitActionLabel(__('admin.academic_student.actions.complete'))
                ->action($step('complete')),

            Action::make('reopen')
                ->label(__('admin.academic_student.actions.reopen'))
                ->icon('lucide-rotate-ccw')
                ->color('gray')
                ->visible(fn (): bool => $this->record->status === StudentStatus::Completed && Gate::allows('reopen', $this->record))
                ->requiresConfirmation()
                ->modalIcon('lucide-rotate-ccw')
                ->modalIconColor('warning')
                ->modalHeading(fn (): string => __('admin.academic_student.confirm.reopen_heading', ['name' => $this->record->full_name]))
                ->modalDescription(__('admin.academic_student.confirm.reopen_body'))
                ->modalSubmitActionLabel(__('admin.academic_student.actions.reopen'))
                ->action($step('reopen')),

            Action::make('openApplication')
                ->label(__('admin.academic_student.actions.open_application'))
                ->icon('lucide-file-text')
                ->color('gray')
                ->url(fn (): string => StudentResource::getUrl('view', ['record' => $this->record])),
        ];
    }

    // ── Visa ─────────────────────────────────────────────────────────────────

    public function visa(): VisaStatus
    {
        return $this->record->visaStatus ?? new VisaStatus(['student_id' => $this->record->getKey()]);
    }

    public function canEditVisa(): bool
    {
        return Gate::allows('update', $this->visa());
    }

    public function editVisaAction(): Action
    {
        return FormModal::apply(Action::make('editVisa'))
            ->label(__('admin.academic_student.visa.edit'))
            ->icon('lucide-pencil')
            ->color('gray')
            ->size('sm')
            ->visible(fn (): bool => $this->canEditVisa())
            ->modalHeading(__('admin.academic_student.visa.edit'))
            ->modalDescription(__('admin.academic_student.visa.edit_description'))
            ->modalIcon('lucide-plane')
            ->modalWidth(MaxWidth::Large)
            ->modalSubmitActionLabel(__('admin.master.save'))
            ->fillForm(fn (): array => [
                'status' => $this->visa()->status ?? VisaProcessStatus::NotStarted,
                'notes' => $this->visa()->notes,
            ])
            ->form([
                Select::make('status')
                    ->label(__('admin.academic_student.visa.status'))
                    ->options(VisaProcessStatus::class)
                    ->required(),
                Textarea::make('notes')
                    ->label(__('admin.academic_student.visa.notes'))
                    ->rows(3)
                    ->maxLength(2000),
            ])
            ->action(function (array $data): void {
                abort_unless($this->canEditVisa(), 403);

                $this->record->visaStatus()->updateOrCreate([], [
                    'status' => $data['status'],
                    'notes' => $data['notes'] ?: null,
                    'updated_by' => auth()->id(),
                ]);
                $this->record->unsetRelation('visaStatus');

                Notification::make()->success()->title(__('admin.academic_student.visa.saved'))->send();
            });
    }

    // ── KTM ──────────────────────────────────────────────────────────────────

    private function activeCard(): ?StudentCard
    {
        return $this->record->activeCard()->first();
    }

    public function issueCardAction(): Action
    {
        return StudentCardActions::issue(Action::make('issueCard'), fn (): Student => $this->record)
            ->size('sm')
            ->visible(fn (): bool => StudentCardActions::canIssue($this->record));
    }

    public function reissueCardAction(): Action
    {
        return StudentCardActions::reissue(Action::make('reissueCard'))
            ->size('sm')
            ->record(fn (): ?StudentCard => $this->activeCard());
    }

    public function revokeCardAction(): Action
    {
        return StudentCardActions::revoke(Action::make('revokeCard'))
            ->size('sm')
            ->outlined()
            ->record(fn (): ?StudentCard => $this->activeCard());
    }

    // ── Data untuk view ──────────────────────────────────────────────────────

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $student = $this->record->loadMissing(['program.faculty', 'nationality', 'homeUniversityCountry', 'agent', 'loa', 'visaStatus.updater']);

        // Selalu baru: aksi KTM di halaman ini mengubah datanya tanpa memuat ulang halaman
        $cards = $student->studentCards()->with('issuer')->latest()->latest('id')->get();
        $canVerify = $this->canVerify();

        return [
            'canVerify' => $canVerify,
            'facts' => $this->facts($student),
            'profile' => ApplicantDetails::profile($student, withEnrollment: true),
            'documents' => $canVerify ? ApplicantDetails::documents($student) : collect(),
            'fees' => $canVerify ? ApplicantDetails::fees($student) : collect(),
            'visa' => $student->visaStatus,
            'canEditVisa' => $this->canEditVisa(),
            'activeCard' => $cards->first(fn (StudentCard $card): bool => $card->isActive()),
            'pastCards' => $cards->reject(fn (StudentCard $card): bool => $card->isActive()),
            'canManageCards' => Gate::allows('create', StudentCard::class),
            'history' => ActivityDescriber::forStudent($student),
        ];
    }

    /**
     * Ringkasan di atas tab: tahap, program, periode, dan apakah mahasiswa sudah bisa masuk portal.
     *
     * @return list<array{label: string, value: ?string, badge?: object, tone?: string}>
     */
    private function facts(Student $student): array
    {
        return array_values(array_filter([
            ['label' => __('admin.dashboard.status'), 'value' => null, 'badge' => $student->status],
            ['label' => __('admin.program.label'), 'value' => $student->program?->name],
            ['label' => __('admin.academic_student.fields.faculty'), 'value' => $student->program?->faculty?->name],
            ['label' => __('admin.applicant.fields.enrollment_period'), 'value' => EnrollmentPeriod::label($student)],
            ['label' => __('admin.academic_student.fields.enrolled_at'), 'value' => $student->enrolled_at?->translatedFormat('j F Y')],
            $student->completed_at ? ['label' => __('admin.academic_student.fields.completed_at'), 'value' => $student->completed_at->translatedFormat('j F Y')] : null,
            [
                'label' => __('admin.academic_student.fields.account'),
                'value' => __('admin.academic_student.account.'.($student->user_id ? 'yes' : 'no')),
                'tone' => $student->user_id ? 'success' : ($student->status->hasAcademicAccess() ? 'warning' : 'gray'),
            ],
            ['label' => __('admin.dashboard.source'), 'value' => $student->agent?->company_name ?? __('admin.dashboard.source_self')],
        ]));
    }
}
