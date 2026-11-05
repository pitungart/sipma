<?php

namespace App\Filament\Support;

use App\Enums\StudentCardStatus;
use App\Enums\StudentStatus;
use App\Models\Student;
use App\Models\StudentCard;
use App\Support\EnrollmentPeriod;
use App\Support\StudentCardArchive;
use App\Workflow\StudentCardWorkflow;
use App\Workflow\WorkflowException;
use Closure;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables\Actions\BulkAction;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Js;
use Livewire\Component;

/**
 * Menerbitkan dan mengunduh banyak KTM sekaligus, per periode masuk atau dari baris yang dicentang.
 * Yang tidak memenuhi syarat dilewati dan disebut, bukan menggagalkan semuanya. Menjalankan periode
 * yang sama lagi hanya menerbitkan yang belum punya KTM aktif.
 */
final class StudentCardBatch
{
    private const NAMES_IN_NOTIFICATION = 5;

    public static function issueByPeriod(Action $action): Action
    {
        $students = fn (?string $period): Collection => self::withoutCard()
            ->inEnrollmentPeriod((string) $period)
            ->with('program')
            ->orderBy('full_name')
            ->get();

        return FormModal::apply($action)
            ->label(__('admin.student_card.actions.issue_period'))
            ->icon('lucide-layers')
            ->modalIcon('lucide-layers')
            ->modalWidth(MaxWidth::Large)
            ->modalHeading(__('admin.student_card.batch.issue_heading'))
            ->modalContent(fn () => view('filament.admin.action-info', ['text' => __('admin.student_card.guide.issue_period').' '.__('admin.student_card.batch.issue_body')]))
            ->modalSubmitActionLabel(__('admin.student_card.actions.issue'))
            ->visible(fn (): bool => Gate::allows('create', StudentCard::class))
            ->form([
                self::periodSelect(fn (): array => EnrollmentPeriod::options(self::withoutCard(), 'admin.student_card.batch.period_issue'))
                    ->live(),

                ...self::planFields(fn (Get $get): Collection => $students($get('period'))),
            ])
            ->action(fn (array $data) => self::issueMany($students($data['period']), self::date($data['valid_until'] ?? null)));
    }

    public static function issueSelected(BulkAction $action): BulkAction
    {
        return FormModal::apply($action)
            ->label(__('admin.student_card.actions.issue'))
            ->icon('lucide-id-card')
            ->modalIcon('lucide-id-card')
            ->modalWidth(MaxWidth::Large)
            ->modalHeading(fn (Collection $records): string => trans_choice('admin.student_card.batch.issue_selected_heading', $records->count(), ['count' => $records->count()]))
            ->modalDescription(__('admin.student_card.batch.issue_body'))
            ->modalSubmitActionLabel(__('admin.student_card.actions.issue'))
            ->visible(fn (): bool => Gate::allows('create', StudentCard::class))
            ->form(fn (Collection $records): array => self::planFields(fn (): Collection => $records))
            ->deselectRecordsAfterCompletion()
            ->action(fn (Collection $records, array $data) => self::issueMany($records, self::date($data['valid_until'] ?? null)));
    }

    public static function downloadByPeriod(Action $action): Action
    {
        return FormModal::apply($action)
            ->label(__('admin.student_card.actions.download_zip'))
            ->icon('lucide-folder-down')
            ->color('gray')
            ->modalIcon('lucide-folder-down')
            ->modalWidth(MaxWidth::Large)
            ->modalHeading(__('admin.student_card.batch.download_heading'))
            ->modalContent(fn () => view('filament.admin.action-info', ['text' => __('admin.student_card.batch.download_body')]))
            ->modalSubmitActionLabel(__('admin.student_card.actions.download_zip'))
            ->form([
                self::periodSelect(fn (): array => EnrollmentPeriod::options(
                    self::visibleStudents()->whereHas('activeCard'),
                    'admin.student_card.batch.period_download',
                )),
            ])
            ->action(function (array $data, Component $livewire): void {
                $cards = StudentCard::query()
                    ->where('status', StudentCardStatus::Active)
                    ->whereIn('student_id', self::visibleStudents()->inEnrollmentPeriod($data['period'])->select('id'))
                    ->pluck('id')
                    ->all();

                self::startDownload($livewire, $cards, StudentCardArchive::periodFilename($data['period']));
            });
    }

    public static function downloadSelected(BulkAction $action): BulkAction
    {
        return $action
            ->label(__('admin.student_card.actions.download_zip'))
            ->icon('lucide-folder-down')
            ->color('gray')
            ->tooltip(__('admin.student_card.guide.download_selected_tip'))
            ->deselectRecordsAfterCompletion()
            ->action(function (Collection $records, Component $livewire): void {
                $cards = $records->filter(fn (StudentCard $card): bool => Gate::allows('download', $card))->modelKeys();

                self::startDownload($livewire, $cards, StudentCardArchive::selectionFilename());
            });
    }

    /**
     * Ringkasan untuk modal: berapa yang terbit dan siapa yang dilewati beserta alasannya.
     *
     * @param  Collection<int, Student>  $students
     * @return array{ready: int, withoutAccount: int, skipped: list<array{name: string, reason: string}>}
     */
    public static function plan(Collection $students, ?Carbon $fallback = null): array
    {
        $workflow = app(StudentCardWorkflow::class);
        $plan = ['ready' => 0, 'withoutAccount' => 0, 'skipped' => []];

        foreach ($students as $student) {
            $blocker = $workflow->issueBlocker($student, self::validUntil($student, $fallback));

            if ($blocker !== null) {
                $plan['skipped'][] = ['name' => $student->full_name, 'reason' => $blocker];

                continue;
            }

            $plan['ready']++;
            $plan['withoutAccount'] += $student->user_id === null ? 1 : 0;
        }

        return $plan;
    }

    /**
     * @param  Collection<int, Student>  $students
     */
    public static function issueMany(Collection $students, ?Carbon $fallback = null): void
    {
        abort_unless(Gate::allows('create', StudentCard::class), 403);

        $workflow = app(StudentCardWorkflow::class);
        $issued = $withoutAccount = 0;
        $skipped = [];

        foreach ($students as $student) {
            try {
                $workflow->issue($student, self::validUntil($student, $fallback));
            } catch (WorkflowException) {
                $skipped[] = $student->full_name;

                continue;
            }

            $issued++;
            $withoutAccount += $student->user_id === null ? 1 : 0;
        }

        Notification::make()
            ->title($issued === 0
                ? __('admin.student_card.batch.none_issued')
                : trans_choice('admin.student_card.batch.issued', $issued, ['count' => $issued]))
            ->body(collect([
                $skipped ? trans_choice('admin.student_card.batch.issued_skipped', count($skipped), ['count' => count($skipped), 'names' => self::names($skipped)]) : null,
                $withoutAccount ? trans_choice('admin.student_card.batch.without_account', $withoutAccount, ['count' => $withoutAccount]) : null,
            ])->filter()->implode(' ') ?: null)
            ->status($issued === 0 ? 'danger' : ($skipped || $withoutAccount ? 'warning' : 'success'))
            ->send();
    }

    /**
     * @param  Closure(Get): Collection<int, Student>  $students
     * @return array<int, DatePicker|Placeholder>
     */
    private static function planFields(Closure $students): array
    {
        return [
            // Hanya muncul bila ada yang tanggalnya tidak bisa dihitung; yang lain tetap memakai hitungannya
            DatePicker::make('valid_until')
                ->label(__('admin.student_card.fields.valid_until'))
                ->helperText(__('admin.student_card.batch.valid_until_hint'))
                ->native(false)
                ->minDate(today()->addDay())
                ->live()
                ->visible(fn (Get $get): bool => $students($get)->contains(fn (Student $student): bool => self::needsDate($student))),

            Placeholder::make('plan')
                ->hiddenLabel()
                ->visible(fn (Get $get): bool => $students($get)->isNotEmpty())
                ->content(fn (Get $get): View => view('filament.admin.student-cards.batch-plan', [
                    'plan' => self::plan($students($get), self::date($get('valid_until'))),
                ])),
        ];
    }

    /**
     * @param  Closure(): array<string, string>  $options
     */
    private static function periodSelect(Closure $options): Select
    {
        return Select::make('period')
            ->label(__('admin.student_card.batch.period'))
            ->options($options)
            ->native(false)
            ->searchable(fn (Select $component): bool => count($component->getOptions()) > 10)
            ->noSearchResultsMessage(__('admin.student_card.batch.period_empty'))
            ->required();
    }

    /**
     * @param  array<int, string>  $cards
     */
    private static function startDownload(Component $livewire, array $cards, string $filename): void
    {
        if ($cards === []) {
            Notification::make()->danger()->title(__('admin.student_card.batch.nothing_to_download'))->send();

            return;
        }

        // Pindah lewat JS: responsnya unduhan, jadi halaman dan tabel tetap di tempatnya
        $livewire->js('window.location.href = '.Js::from(StudentCardArchive::prepare($cards, $filename)));
    }

    private static function needsDate(Student $student): bool
    {
        $default = StudentCardWorkflow::defaultValidUntil($student);

        return $default === null || ! $default->isAfter(today());
    }

    private static function validUntil(Student $student, ?Carbon $fallback): ?Carbon
    {
        return self::needsDate($student) ? $fallback : StudentCardWorkflow::defaultValidUntil($student);
    }

    private static function visibleStudents(): Builder
    {
        return Student::query()->visibleTo(Filament::auth()->user());
    }

    private static function withoutCard(): Builder
    {
        return self::visibleStudents()
            ->where('status', StudentStatus::Enrolled)
            ->whereDoesntHave('activeCard');
    }

    /**
     * @param  list<string>  $names
     */
    private static function names(array $names): string
    {
        $shown = implode(', ', array_slice($names, 0, self::NAMES_IN_NOTIFICATION));
        $rest = count($names) - self::NAMES_IN_NOTIFICATION;

        return $rest > 0 ? $shown.' '.__('admin.student_card.batch.and_more', ['count' => $rest]) : $shown;
    }

    private static function date(?string $value): ?Carbon
    {
        return filled($value) ? Carbon::parse($value) : null;
    }
}
