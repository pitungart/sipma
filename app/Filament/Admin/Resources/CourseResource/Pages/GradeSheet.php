<?php

namespace App\Filament\Admin\Resources\CourseResource\Pages;

use App\Enums\Grade;
use App\Enums\StudentCourseStatus;
use App\Enums\StudentStatus;
use App\Filament\Admin\Resources\CourseResource;
use App\Models\Course;
use App\Models\StudentCourse;
use App\Workflow\AcademicRecordWorkflow;
use App\Workflow\WorkflowException;
use Filament\Facades\Filament;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\Rule;

/**
 * Lembar nilai per MK: semua mahasiswa Aktif yang menempuh MK ini, disimpan sekaligus. Hak menilai
 * mengikuti fakultas mahasiswa (K3), jadi Admin Fakultas hanya melihat mahasiswa fakultasnya.
 *
 * @property Course $record
 * @property Form $form
 */
class GradeSheet extends Page implements HasForms
{
    use InteractsWithForms, InteractsWithRecord;

    protected static string $resource = CourseResource::class;

    protected static string $view = 'filament.admin.courses.grade-sheet';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        abort_unless(Gate::allows('update', $this->record) && ! $this->record->trashed(), 403);

        $this->form->fill([
            'grades' => $this->rows()->mapWithKeys(fn (StudentCourse $row): array => [$row->getKey() => $row->grade?->value])->all(),
        ]);
    }

    public function getTitle(): string|Htmlable
    {
        return __('admin.student_course.sheet.title', ['course' => $this->record->code]);
    }

    public function getSubheading(): ?string
    {
        return collect([$this->record->localizedName(), $this->record->program?->name])->filter()->implode(' · ');
    }

    /**
     * @return array<int|string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [
            __('admin.groups.academic'),
            CourseResource::getUrl() => CourseResource::getPluralModelLabel(),
            $this->record->code,
        ];
    }

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->schema($this->rows()->map(fn (StudentCourse $row): Grid => Grid::make(['default' => 1, 'md' => 3])->schema([
                Placeholder::make("student_{$row->getKey()}")
                    ->hiddenLabel()
                    ->columnSpan(['default' => 1, 'md' => 2])
                    ->content(new HtmlString(Blade::render(
                        '<div class="sipma-person"><x-sipma.avatar :name="$student->full_name" :id="$student->getKey()" /><div><div class="sipma-person-name">{{ $student->full_name }}</div><div class="sipma-person-meta">{{ $student->registration_number }}</div></div></div>',
                        ['student' => $row->student],
                    ))),

                Select::make("grades.{$row->getKey()}")
                    ->hiddenLabel()
                    ->placeholder(__('admin.student_course.sheet.no_grade'))
                    ->options(Grade::class)
                    ->rule(Rule::enum(Grade::class)),
            ]))->all());
    }

    public function save(): void
    {
        abort_unless(Gate::allows('update', $this->record), 403);

        $grades = $this->form->getState()['grades'] ?? [];
        $changed = 0;

        try {
            foreach ($this->rows()->groupBy('student_id') as $rows) {
                $student = $rows->first()->student;

                if (! Gate::allows('gradeCourses', $student)) {
                    continue;
                }

                $changed += app(AcademicRecordWorkflow::class)->saveGrades(
                    $student,
                    $rows->mapWithKeys(fn (StudentCourse $row): array => [$row->getKey() => Grade::tryFrom((string) ($grades[$row->getKey()] ?? ''))])->all(),
                );
            }
        } catch (WorkflowException $e) {
            Notification::make()->danger()->title($e->getMessage())->send();

            return;
        }

        Notification::make()
            ->success()
            ->title($changed > 0 ? __('admin.student_course.success.graded', ['count' => $changed]) : __('admin.student_course.success.no_grade_change'))
            ->send();
    }

    /**
     * Baris yang boleh dinilai admin ini: mahasiswa Aktif dalam aksesnya, tanpa yang di-drop.
     *
     * @return Collection<int, StudentCourse>
     */
    public function rows(): Collection
    {
        return $this->record->studentCourses()
            ->where('status', '!=', StudentCourseStatus::Dropped)
            ->whereHas('student', fn (Builder $student) => $student
                ->where('status', StudentStatus::Enrolled)
                ->visibleTo(Filament::auth()->user()))
            ->with('student')
            ->get()
            ->sortBy(fn (StudentCourse $row): ?string => $row->student?->full_name)
            ->values();
    }
}
