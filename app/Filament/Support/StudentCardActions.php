<?php

namespace App\Filament\Support;

use App\Enums\NumberType;
use App\Enums\StudentCardStatus;
use App\Enums\StudentStatus;
use App\Models\Student;
use App\Models\StudentCard;
use App\Support\EnrollmentPeriod;
use App\Support\Numbering;
use App\Workflow\StudentCardWorkflow;
use App\Workflow\WorkflowException;
use Closure;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

/**
 * Aksi KTM yang sama di menu KTM digital dan tab KTM detail Mahasiswa Asing. Bisa dipasang pada
 * action halaman maupun action tabel; KTM yang diproses diambil dari record action tersebut.
 */
final class StudentCardActions
{
    /**
     * @template T of \Filament\Actions\Action|\Filament\Tables\Actions\Action
     *
     * @param  T  $action
     * @param  (Closure(): Student)|null  $student  kosong = staf memilih mahasiswanya di form
     * @return T
     */
    public static function issue($action, ?Closure $student = null)
    {
        $selected = fn (Get $get): ?Student => $student ? $student() : Student::query()->with('program')->find($get('student_id'));

        return FormModal::apply($action)
            ->label(__('admin.student_card.actions.issue'))
            ->icon('lucide-id-card')
            ->modalIcon('lucide-id-card')
            ->modalWidth(MaxWidth::Large)
            ->modalHeading(fn (): string => $student
                ? __('admin.student_card.confirm.issue_for', ['name' => $student()->full_name])
                : __('admin.student_card.confirm.issue_heading'))
            ->modalContent(fn () => view('filament.admin.action-info', [
                'text' => __('admin.student_card.guide.issue').' '.__('admin.student_card.confirm.issue_body'),
                'warning' => $student && $student()->user_id === null ? __('admin.student_card.confirm.no_account') : null,
            ]))
            ->modalSubmitActionLabel(__('admin.student_card.actions.issue'))
            ->fillForm(fn (): array => ['valid_until' => $student ? StudentCardWorkflow::defaultValidUntil($student())?->toDateString() : null])
            ->form(array_values(array_filter([
                $student ? null : Select::make('student_id')
                    ->label(__('admin.student_card.fields.student'))
                    ->options(fn (): array => self::eligibleStudents())
                    ->searchable()
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn (Set $set, ?string $state) => $set(
                        'valid_until',
                        StudentCardWorkflow::defaultValidUntil(Student::query()->with('program')->findOrNew($state))?->toDateString(),
                    )),

                self::validUntilField()
                    ->helperText(fn (Get $get): string => self::validUntilHint($selected($get))),

                self::numberField(),
            ])))
            ->action(function (array $data) use ($student): void {
                abort_unless(Gate::allows('create', StudentCard::class), 403);

                $target = $student ? $student() : Student::query()->visibleTo(Filament::auth()->user())->findOrFail($data['student_id']);

                self::run('issued', fn (StudentCardWorkflow $workflow): StudentCard => $workflow->issue(
                    $target,
                    self::date($data['valid_until'] ?? null),
                    $data['card_number'] ?? null,
                ));
            });
    }

    /**
     * @template T of \Filament\Actions\Action|\Filament\Tables\Actions\Action
     *
     * @param  T  $action
     * @return T
     */
    public static function reissue($action)
    {
        return FormModal::apply($action)
            ->label(__('admin.student_card.actions.reissue'))
            ->icon('lucide-refresh-cw')
            ->color('gray')
            ->modalIcon('lucide-refresh-cw')
            ->modalWidth(MaxWidth::Large)
            ->modalHeading(fn (?StudentCard $record): string => __('admin.student_card.confirm.reissue_heading', ['number' => $record?->card_number]))
            ->modalContent(fn () => view('filament.admin.action-info', ['text' => __('admin.student_card.guide.reissue')]))
            ->modalSubmitActionLabel(__('admin.student_card.actions.reissue'))
            ->visible(fn (?StudentCard $record): bool => self::canReissue($record))
            ->fillForm(fn (StudentCard $record): array => ['valid_until' => $record->valid_until->toDateString()])
            ->form([
                self::reasonField(),
                self::validUntilField()->helperText(__('admin.student_card.hints.valid_keep')),
                self::numberField(),
            ])
            ->action(function (StudentCard $record, array $data): void {
                abort_unless(Gate::allows('reissue', $record), 403);

                self::run('reissued', fn (StudentCardWorkflow $workflow): StudentCard => $workflow->reissue(
                    $record,
                    $data['reason'],
                    self::date($data['valid_until'] ?? null),
                    $data['card_number'] ?? null,
                ));
            });
    }

    /**
     * @template T of \Filament\Actions\Action|\Filament\Tables\Actions\Action
     *
     * @param  T  $action
     * @return T
     */
    public static function revoke($action)
    {
        return FormModal::apply($action)
            ->label(__('admin.student_card.actions.revoke'))
            ->icon('lucide-ban')
            ->color('danger')
            ->modalIcon('lucide-ban')
            ->modalIconColor('danger')
            ->modalWidth(MaxWidth::Large)
            ->modalHeading(fn (?StudentCard $record): string => __('admin.student_card.confirm.revoke_heading', ['number' => $record?->card_number]))
            ->modalContent(fn () => view('filament.admin.action-info', ['text' => __('admin.student_card.guide.revoke')]))
            ->modalSubmitActionLabel(__('admin.student_card.actions.revoke'))
            ->visible(fn (?StudentCard $record): bool => $record?->isActive() === true && Gate::allows('revoke', $record))
            ->form([self::reasonField()])
            ->action(function (StudentCard $record, array $data): void {
                abort_unless(Gate::allows('revoke', $record), 403);

                self::run('revoked', fn (StudentCardWorkflow $workflow): StudentCard => $workflow->revoke($record, $data['reason']));
            });
    }

    public static function canIssue(Student $student): bool
    {
        return $student->status === StudentStatus::Enrolled
            && Gate::allows('create', StudentCard::class)
            && $student->studentCards()->where('status', StudentCardStatus::Active)->doesntExist();
    }

    /**
     * KTM mahasiswa yang sudah Selesai hanya bisa dicabut, tidak diganti.
     */
    public static function canReissue(?StudentCard $card): bool
    {
        return $card?->isActive() === true
            && $card->student?->status === StudentStatus::Enrolled
            && Gate::allows('reissue', $card);
    }

    /**
     * Mahasiswa Aktif yang belum punya KTM aktif.
     *
     * @return array<string, string>
     */
    private static function eligibleStudents(): array
    {
        return Student::query()
            ->visibleTo(Filament::auth()->user())
            ->where('status', StudentStatus::Enrolled)
            ->whereDoesntHave('activeCard')
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'registration_number'])
            ->mapWithKeys(fn (Student $student): array => [
                $student->getKey() => collect([$student->full_name, $student->registration_number])->filter()->implode(' · '),
            ])
            ->all();
    }

    private static function validUntilHint(?Student $student): string
    {
        return match (true) {
            $student === null => __('admin.student_card.hints.valid_pick'),
            StudentCardWorkflow::defaultValidUntil($student) === null => __('admin.student_card.hints.valid_manual'),
            default => __('admin.student_card.hints.valid_from_duration', [
                'period' => EnrollmentPeriod::label($student),
                'months' => $student->program?->duration_months,
            ]),
        };
    }

    private static function validUntilField(): DatePicker
    {
        return DatePicker::make('valid_until')
            ->label(__('admin.student_card.fields.valid_until'))
            ->native(false)
            ->required()
            ->minDate(today()->addDay());
    }

    private static function numberField(): TextInput
    {
        return TextInput::make('card_number')
            ->label(__('admin.student_card.fields.card_number'))
            ->placeholder(fn (): string => Numbering::preview(NumberType::StudentCard))
            ->helperText(__('admin.student_card.hints.number'))
            ->maxLength(50)
            ->unique('student_cards', 'card_number');
    }

    private static function reasonField(): Textarea
    {
        return Textarea::make('reason')
            ->label(__('admin.student_card.fields.reason'))
            ->helperText(__('admin.student_card.hints.reason'))
            ->required()
            ->rows(3)
            ->maxLength(255);
    }

    private static function date(?string $value): ?Carbon
    {
        return filled($value) ? Carbon::parse($value) : null;
    }

    /**
     * @param  'issued'|'reissued'|'revoked'  $done
     * @param  Closure(StudentCardWorkflow): StudentCard  $step
     */
    private static function run(string $done, Closure $step): void
    {
        try {
            $card = $step(app(StudentCardWorkflow::class));
        } catch (WorkflowException $e) {
            Notification::make()->danger()->title($e->getMessage())->send();

            return;
        }

        $notification = Notification::make()->title(__("admin.student_card.success.{$done}", ['number' => $card->card_number]));

        // Tanpa akun portal, KTM baru tidak sampai ke mahasiswa
        if ($done !== 'revoked' && $card->student->user_id === null) {
            $notification->warning()->body(__('admin.student_card.success.no_account'));
        } else {
            $notification->success();
        }

        $notification->send();
    }
}
