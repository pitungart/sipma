<?php

namespace App\Filament\Admin\Resources\AcademicStudentResource\Pages;

use App\Enums\Grade;
use App\Enums\StudentCourseStatus;
use App\Enums\StudentStatus;
use App\Enums\VisaProcessStatus;
use App\Filament\Admin\Resources\AcademicStudentResource;
use App\Filament\Admin\Resources\StudentResource;
use App\Filament\Support\FilePreview;
use App\Filament\Support\FormModal;
use App\Filament\Support\StudentCardActions;
use App\Models\Course;
use App\Models\Student;
use App\Models\StudentCard;
use App\Models\StudentCourse;
use App\Models\VisaStatus;
use App\Support\AcademicSummary;
use App\Support\ActivityDescriber;
use App\Support\ApplicantDetails;
use App\Support\EnrollmentPeriod;
use App\Support\StudentCardDocument;
use App\Support\TeachingRecords;
use App\Workflow\AcademicRecordWorkflow;
use App\Workflow\WorkflowException;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Detail Mahasiswa Asing (TP-1 F2): ringkasan tahap pasca-LOA + tab Info · Visa · KTM · Rubrik · SK Mengajar · Riwayat.
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
            'rubric' => __('admin.academic_student.tabs.rubric'),
            'decrees' => __('admin.academic_student.tabs.decrees'),
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
                ->modalDescription(fn (): string => AcademicStudentResource::completeDescription($this->record))
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

    // ── Dokumen ──────────────────────────────────────────────────────────────

    public function previewDocumentAction(): Action
    {
        return FilePreview::action('previewDocument', function (array $arguments): array {
            abort_unless($this->canVerify(), 403);

            // Argumen datang dari browser: selalu dicari di dalam mahasiswa ini
            $document = $this->record->documents()->findOrFail($arguments['document'] ?? null);

            return [
                'url' => route('files.document', $document),
                'image' => str_starts_with((string) $document->mime_type, 'image/'),
                'title' => $document->type->getLabel(),
            ];
        });
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

    // ── Rubrik ───────────────────────────────────────────────────────────────

    /**
     * Daftar MK hanya bisa diubah selagi mahasiswa Aktif.
     */
    public function canManageCourses(): bool
    {
        return $this->record->status === StudentStatus::Enrolled && Gate::allows('manageCourses', $this->record);
    }

    public function applyPackageAction(): Action
    {
        return Action::make('applyPackage')
            ->label(__('admin.student_course.actions.apply'))
            ->icon('lucide-list-checks')
            ->color('gray')
            ->size('sm')
            ->visible(fn (): bool => $this->canManageCourses())
            ->requiresConfirmation()
            ->modalIcon('lucide-list-checks')
            ->modalHeading(__('admin.student_course.confirm.apply_heading'))
            ->modalDescription(__('admin.student_course.confirm.apply_body'))
            ->modalSubmitActionLabel(__('admin.student_course.actions.apply'))
            ->action(function (): void {
                abort_unless($this->canManageCourses(), 403);

                $this->runCourseStep(function (AcademicRecordWorkflow $workflow): string {
                    $added = $workflow->applyPackage($this->record);

                    return $added > 0
                        ? __('admin.student_course.success.applied', ['count' => $added])
                        : __('admin.student_course.success.nothing_to_apply');
                });
            });
    }

    public function addCourseAction(): Action
    {
        return FormModal::apply(Action::make('addCourse'))
            ->label(__('admin.student_course.actions.add'))
            ->icon('lucide-plus')
            ->size('sm')
            ->visible(fn (): bool => $this->canManageCourses())
            ->modalIcon('lucide-book-open')
            ->modalHeading(__('admin.student_course.actions.add'))
            ->modalDescription(__('admin.student_course.confirm.add_body'))
            ->modalWidth(MaxWidth::Large)
            ->modalSubmitActionLabel(__('admin.student_course.actions.add'))
            ->form([
                Select::make('course_id')
                    ->label(__('admin.course.label'))
                    ->options(fn (): array => $this->availableCourses())
                    // MK di luar daftar (fakultas lain) ditolak walau dikirim paksa lewat Livewire
                    ->in(fn (Select $component): array => array_keys($component->getOptions()))
                    ->searchable()
                    ->required(),
            ])
            ->action(function (array $data): void {
                abort_unless($this->canManageCourses(), 403);

                $this->runCourseStep(function (AcademicRecordWorkflow $workflow) use ($data): string {
                    $row = $workflow->addCourse($this->record, Course::query()->findOrFail($data['course_id']));

                    return __('admin.student_course.success.added', ['course' => $row->course->code]);
                });
            });
    }

    /**
     * Nilai: Admin Fakultas selama mahasiswa Aktif, koreksi setelah Selesai hanya Super Admin.
     */
    public function canGradeCourses(): bool
    {
        return $this->record->status->hasAcademicAccess() && Gate::allows('gradeCourses', $this->record);
    }

    public function gradeCoursesAction(): Action
    {
        $rows = fn () => StudentCourse::inPackageOrder($this->record->studentCourses()
            ->where('status', '!=', StudentCourseStatus::Dropped)
            ->with('course')
            ->get());

        return FormModal::apply(Action::make('gradeCourses'))
            ->label(__('admin.student_course.actions.grade'))
            ->icon('lucide-clipboard-pen')
            ->color('gray')
            ->size('sm')
            ->visible(fn (): bool => $this->canGradeCourses() && $rows()->isNotEmpty())
            ->modalIcon('lucide-clipboard-pen')
            ->modalHeading(__('admin.student_course.actions.grade'))
            ->modalDescription(__('admin.student_course.confirm.grade_body'))
            ->modalWidth(MaxWidth::Large)
            ->modalSubmitActionLabel(__('admin.master.save'))
            ->fillForm(fn (): array => ['grades' => $rows()->mapWithKeys(fn (StudentCourse $row): array => [$row->getKey() => $row->grade?->value])->all()])
            ->form(fn (): array => $rows()->map(fn (StudentCourse $row): Select => Select::make("grades.{$row->getKey()}")
                ->label("{$row->course?->code} · {$row->course?->localizedName()}")
                ->placeholder(__('admin.student_course.sheet.no_grade'))
                ->options(Grade::class)
                ->rule(Rule::enum(Grade::class)))->all())
            ->action(function (array $data) use ($rows): void {
                abort_unless($this->canGradeCourses(), 403);

                $this->runCourseStep(function (AcademicRecordWorkflow $workflow) use ($data, $rows): string {
                    $changed = $workflow->saveGrades($this->record, $rows()->mapWithKeys(fn (StudentCourse $row): array => [
                        $row->getKey() => Grade::tryFrom((string) ($data['grades'][$row->getKey()] ?? '')),
                    ])->all());

                    return $changed > 0
                        ? __('admin.student_course.success.graded', ['count' => $changed])
                        : __('admin.student_course.success.no_grade_change');
                });
            });
    }

    public function dropCourseAction(): Action
    {
        return FormModal::apply(Action::make('dropCourse'))
            ->iconButton()
            ->icon('lucide-circle-minus')
            ->color('gray')
            ->tooltip(__('admin.student_course.actions.drop'))
            ->visible(fn (): bool => $this->canManageCourses())
            ->modalIcon('lucide-circle-minus')
            ->modalIconColor('warning')
            ->modalHeading(fn (array $arguments): string => __('admin.student_course.confirm.drop_heading', ['course' => $this->courseRow($arguments)->course?->code]))
            ->modalDescription(__('admin.student_course.confirm.drop_body'))
            ->modalWidth(MaxWidth::Large)
            ->modalSubmitActionLabel(__('admin.student_course.actions.drop'))
            ->form([
                Textarea::make('note')
                    ->label(__('admin.student_course.fields.drop_note'))
                    ->rows(3)
                    ->maxLength(1000),
            ])
            ->action(function (array $arguments, array $data): void {
                abort_unless($this->canManageCourses(), 403);

                $this->runCourseStep(function (AcademicRecordWorkflow $workflow) use ($arguments, $data): string {
                    $row = $workflow->drop($this->courseRow($arguments), $data['note'] ?? null);

                    return __('admin.student_course.success.dropped', ['course' => $row->course?->code]);
                });
            });
    }

    public function restoreCourseAction(): Action
    {
        return Action::make('restoreCourse')
            ->iconButton()
            ->icon('lucide-undo-2')
            ->color('gray')
            ->tooltip(__('admin.student_course.actions.restore'))
            ->visible(fn (): bool => $this->canManageCourses())
            ->requiresConfirmation()
            ->modalIcon('lucide-undo-2')
            ->modalHeading(fn (array $arguments): string => __('admin.student_course.confirm.restore_heading', ['course' => $this->courseRow($arguments)->course?->code]))
            ->modalDescription(__('admin.student_course.confirm.restore_body'))
            ->modalSubmitActionLabel(__('admin.student_course.actions.restore'))
            ->action(function (array $arguments): void {
                abort_unless($this->canManageCourses(), 403);

                $this->runCourseStep(function (AcademicRecordWorkflow $workflow) use ($arguments): string {
                    $row = $workflow->restore($this->courseRow($arguments));

                    return __('admin.student_course.success.restored', ['course' => $row->course?->code]);
                });
            });
    }

    /**
     * Baris selalu dicari di dalam MK mahasiswa ini, bukan dari ID yang dikirim browser saja.
     */
    private function courseRow(array $arguments): StudentCourse
    {
        return $this->record->studentCourses()->with('course')->findOrFail($arguments['row'] ?? null);
    }

    /**
     * MK aktif yang belum dimiliki mahasiswa, dalam batas akses admin; lintas program diperbolehkan.
     *
     * @return array<string, string>
     */
    private function availableCourses(): array
    {
        return Course::query()
            ->active()
            ->whereHas('program', fn (Builder $program) => $program->visibleTo(auth()->user()))
            ->whereNotIn('id', $this->record->studentCourses()->select('course_id'))
            ->with('program:id,name')
            ->packageOrder()
            ->get()
            ->mapWithKeys(fn (Course $course): array => [
                $course->getKey() => "{$course->code} · {$course->localizedName()} ({$course->program?->name})",
            ])
            ->all();
    }

    /**
     * @param  Closure(AcademicRecordWorkflow): string  $step  mengembalikan judul notifikasi berhasil
     */
    private function runCourseStep(Closure $step): void
    {
        try {
            Notification::make()->success()->title($step(app(AcademicRecordWorkflow::class)))->send();
        } catch (WorkflowException $e) {
            Notification::make()->danger()->title($e->getMessage())->send();
        }
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
            'photoUrl' => StudentCardDocument::photo($student) ? route('files.student-photo', $student) : null,
            'facts' => $this->facts($student),
            'profile' => ApplicantDetails::profile($student, withEnrollment: true),
            'documents' => $canVerify ? ApplicantDetails::documents($student) : collect(),
            'fees' => $canVerify ? ApplicantDetails::fees($student) : collect(),
            'visa' => $student->visaStatus,
            'canEditVisa' => $this->canEditVisa(),
            'activeCard' => $cards->first(fn (StudentCard $card): bool => $card->isActive()),
            'pastCards' => $cards->reject(fn (StudentCard $card): bool => $card->isActive()),
            'canManageCards' => Gate::allows('create', StudentCard::class),
            'courses' => $courses = StudentCourse::inPackageOrder($student->studentCourses()->with('course.program')->get()),
            'courseSummary' => AcademicSummary::from($courses),
            'teaching' => $teaching = TeachingRecords::forStudent($student),
            'teachers' => TeachingRecords::teachersByCourse($teaching),
            'canManageCourses' => $this->canManageCourses(),
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
