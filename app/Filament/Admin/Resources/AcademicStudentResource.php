<?php

namespace App\Filament\Admin\Resources;

use App\Enums\AccountProvision;
use App\Enums\StudentCourseStatus;
use App\Enums\StudentStatus;
use App\Enums\UserRole;
use App\Filament\Admin\Resources\AcademicStudentResource\Pages;
use App\Filament\Admin\Resources\Concerns\AcademicModule;
use App\Models\Country;
use App\Models\Faculty;
use App\Models\Program;
use App\Models\Student;
use App\Support\EnrollmentPeriod;
use App\Workflow\StudentWorkflow;
use App\Workflow\WorkflowException;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * TP-1 Mahasiswa Asing (mockup KUI Gambar 3.2): mahasiswa yang sudah menerima LOA — LOA terbit
 * (siap diaktifkan), Aktif, dan Selesai — terpisah dari antrean verifikasi Pendaftar. Datanya
 * tabel `students` yang sama; data pribadi tetap diubah lewat Pendaftar, jadi tidak ada form di sini.
 */
class AcademicStudentResource extends Resource
{
    use AcademicModule;

    protected static ?string $model = Student::class;

    protected static ?string $slug = 'international-students';

    protected static ?string $navigationIcon = 'lucide-graduation-cap';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'full_name';

    public static function getModelLabel(): string
    {
        return __('admin.academic_student.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.academic_student.plural');
    }

    /**
     * Jumlah yang menunggu diaktifkan, seperti angka "diajukan" di menu Pendaftar.
     */
    public static function getNavigationBadge(): ?string
    {
        $count = static::getEloquentQuery()->where('status', StudentStatus::LoaIssued)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return StudentStatus::LoaIssued->getColor();
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->visibleTo(Filament::auth()->user())
            ->whereIn('status', StudentStatus::acceptedStatuses())
            ->with(['program.faculty', 'nationality', 'agent:id,company_name', 'activeCard'])
            ->withCount(['studentCourses as courses_taken' => fn (Builder $query): Builder => $query->where('status', '!=', StudentCourseStatus::Dropped)]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        $superAdmin = fn (): bool => Filament::auth()->user()?->hasRole(UserRole::SuperAdmin) === true;

        return $table
            ->defaultSort('enrolled_at', 'desc')
            ->searchPlaceholder(__('admin.common.search', ['label' => Str::lower(__('admin.academic_student.plural'))]))
            ->recordUrl(fn (Student $record): string => static::getUrl('view', ['record' => $record]))
            ->paginationPageOptions([10, 25, 50, 100])
            ->defaultPaginationPageOption(10)
            ->emptyStateIcon('lucide-graduation-cap')
            ->emptyStateHeading(__('admin.academic_student.empty_heading'))
            ->emptyStateDescription(__('admin.academic_student.empty_description'))
            ->columns([
                Tables\Columns\ViewColumn::make('full_name')
                    ->label(__('admin.dashboard.applicant_name'))
                    ->view('filament.admin.columns.student-identity')
                    ->searchable(['full_name', 'email', 'passport_number', 'registration_number'])
                    ->sortable(),

                Tables\Columns\TextColumn::make('program.name')
                    ->label(__('admin.program.label'))
                    ->description(fn (Student $record): ?string => $record->program?->faculty?->name)
                    ->wrap()
                    ->sortable(),

                Tables\Columns\TextColumn::make('enrollment_period')
                    ->label(__('admin.applicant.fields.enrollment_period'))
                    ->state(fn (Student $record): ?string => EnrollmentPeriod::label($record))
                    ->placeholder('—')
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query
                        ->orderBy('enrollment_year', $direction)
                        ->orderBy('enrollment_month', $direction)),

                Tables\Columns\TextColumn::make('enrolled_at')
                    ->label(__('admin.academic_student.fields.enrolled_at'))
                    ->date('j M Y')
                    ->placeholder('—')
                    ->sortable(),

                // Mahasiswa via agen baru punya akun setelah diaktifkan (StudentAccounts)
                Tables\Columns\TextColumn::make('account')
                    ->label(__('admin.academic_student.fields.account'))
                    ->state(fn (Student $record): string => __('admin.academic_student.account.'.($record->user_id ? 'yes' : 'no')))
                    ->badge()
                    ->color(fn (Student $record): string => match (true) {
                        $record->user_id !== null => 'success',
                        $record->status->hasAcademicAccess() => 'warning',
                        default => 'gray',
                    })
                    ->icon(fn (Student $record): string => $record->user_id ? 'lucide-circle-check' : 'lucide-circle-minus')
                    ->tooltip(fn (Student $record): ?string => match (true) {
                        $record->user_id !== null => null,
                        $record->status->hasAcademicAccess() => __('admin.academic_student.account.failed_hint'),
                        default => __('admin.academic_student.account.pending_hint'),
                    }),

                // Hanya mahasiswa Aktif yang bisa diterbitkan KTM; belum ada = perlu ditindaklanjuti
                Tables\Columns\TextColumn::make('card')
                    ->label(__('admin.academic_student.fields.card'))
                    ->state(fn (Student $record): string => __('admin.student_card.'.($record->activeCard ? 'yes' : 'no')))
                    ->badge()
                    ->color(fn (Student $record): string => match (true) {
                        $record->activeCard !== null => 'success',
                        $record->status === StudentStatus::Enrolled => 'warning',
                        default => 'gray',
                    })
                    ->icon(fn (Student $record): string => $record->activeCard ? 'lucide-circle-check' : 'lucide-circle-minus')
                    ->tooltip(fn (Student $record): ?string => $record->activeCard?->card_number),

                // Mahasiswa Aktif tanpa MK perlu ditindaklanjuti (program tanpa paket)
                Tables\Columns\TextColumn::make('courses_taken')
                    ->label(__('admin.academic_student.fields.courses'))
                    ->badge()
                    ->color(fn (Student $record): string => $record->status === StudentStatus::Enrolled && (int) $record->courses_taken === 0 ? 'warning' : 'gray'),

                Tables\Columns\TextColumn::make('status')
                    ->label(__('admin.dashboard.status'))
                    ->badge(),

                Tables\Columns\TextColumn::make('agent.company_name')
                    ->label(__('admin.dashboard.source'))
                    ->placeholder(__('admin.dashboard.source_self'))
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('faculty')
                    ->label(__('admin.academic_student.fields.faculty'))
                    ->visible($superAdmin)
                    ->options(fn (): array => Faculty::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->whereHas('program', fn (Builder $program) => $program->where('faculty_id', $data['value']))
                        : $query),

                Tables\Filters\SelectFilter::make('program_id')
                    ->label(__('admin.program.label'))
                    ->options(fn (): array => Program::query()->visibleTo(Filament::auth()->user())->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable(),

                Tables\Filters\SelectFilter::make('enrollment_year')
                    ->label(__('admin.applicant.fields.enrollment_year'))
                    ->options(fn (): array => EnrollmentPeriod::years()),

                Tables\Filters\SelectFilter::make('enrollment_month')
                    ->label(__('admin.applicant.fields.enrollment_month'))
                    ->options(fn (): array => EnrollmentPeriod::months())
                    ->searchable(),

                // Hanya negara yang memang ada di daftar ini
                Tables\Filters\SelectFilter::make('nationality_code')
                    ->label(__('admin.academic_student.fields.nationality'))
                    ->options(fn (): array => Country::query()
                        ->whereIn('code', static::getEloquentQuery()->select('nationality_code'))
                        ->orderByName()
                        ->get()
                        ->pluck('name', 'code')
                        ->all())
                    ->searchable(),
            ])
            ->actions([
                Tables\Actions\Action::make('enroll')
                    ->iconButton()
                    ->icon('lucide-graduation-cap')
                    ->color('success')
                    ->tooltip(__('admin.academic_student.actions.enroll'))
                    ->visible(fn (Student $record): bool => $record->status === StudentStatus::LoaIssued && Gate::allows('enroll', $record))
                    ->requiresConfirmation()
                    ->modalIcon('lucide-graduation-cap')
                    ->modalIconColor('success')
                    ->modalHeading(fn (Student $record): string => __('admin.academic_student.confirm.enroll_heading', ['name' => $record->full_name]))
                    ->modalDescription(fn (Student $record): string => static::enrollDescription($record))
                    ->modalSubmitActionLabel(__('admin.academic_student.actions.enroll'))
                    ->action(fn (Student $record) => static::runStep($record, 'enroll')),

                Tables\Actions\ViewAction::make()
                    ->iconButton()
                    ->icon('lucide-eye')
                    ->color('gray')
                    ->tooltip(__('admin.applicant.open')),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('enrollSelected')
                    ->label(__('admin.academic_student.actions.enroll_bulk'))
                    ->icon('lucide-graduation-cap')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalIcon('lucide-graduation-cap')
                    ->modalIconColor('success')
                    ->modalHeading(fn (Collection $records): string => __('admin.academic_student.confirm.bulk_heading', ['count' => $records->count()]))
                    ->modalDescription(__('admin.academic_student.confirm.bulk_body'))
                    ->modalSubmitActionLabel(__('admin.academic_student.actions.enroll'))
                    ->deselectRecordsAfterCompletion()
                    ->action(fn (Collection $records) => static::enrollMany($records)),
            ]);
    }

    // ── Langkah pasca-LOA (dipakai daftar & halaman detail) ──────────────────

    /**
     * Isi konfirmasi Aktifkan; mahasiswa tanpa akun diberi tahu bahwa akun & undangan akan dibuat.
     */
    public static function enrollDescription(Student $student): string
    {
        return collect([
            __('admin.academic_student.confirm.enroll_body'),
            $student->user_id === null ? __('admin.academic_student.confirm.enroll_invite', ['email' => $student->email]) : null,
        ])->filter()->implode(' ');
    }

    /**
     * enroll | complete | reopen lewat StudentWorkflow. Ability bernama sama di StudentPolicy.
     * Hasil Aktifkan menyebut nasib akun portal; akun yang gagal disiapkan tampil sebagai peringatan.
     */
    public static function runStep(Student $student, string $step): void
    {
        abort_unless(Gate::allows($step, $student), 403);

        try {
            $result = app(StudentWorkflow::class)->{$step}($student);
        } catch (WorkflowException $e) {
            Notification::make()->danger()->title($e->getMessage())->send();

            return;
        }

        $done = ['enroll' => 'enrolled', 'complete' => 'completed', 'reopen' => 'reopened'][$step];
        $notification = Notification::make()->title(__("admin.academic_student.success.{$done}", ['name' => $student->full_name]));

        if ($result instanceof AccountProvision) {
            $notification->body($result->getLabel())->status($result->hasAccount() ? 'success' : 'warning');
        } else {
            $notification->success();
        }

        $notification->send();
    }

    /**
     * Aktifkan beberapa sekaligus: yang bukan LOA terbit atau di luar akses dilewati, bukan menggagalkan semuanya.
     *
     * @param  Collection<int, Student>  $students
     */
    public static function enrollMany(Collection $students): void
    {
        $workflow = app(StudentWorkflow::class);
        $enrolled = $skipped = $withoutAccount = 0;

        foreach ($students as $student) {
            if ($student->status !== StudentStatus::LoaIssued || ! Gate::allows('enroll', $student)) {
                $skipped++;

                continue;
            }

            try {
                $account = $workflow->enroll($student);
            } catch (WorkflowException) {
                $skipped++;

                continue;
            }

            $enrolled++;
            $withoutAccount += $account->hasAccount() ? 0 : 1;
        }

        Notification::make()
            ->title(__('admin.academic_student.success.bulk', ['count' => $enrolled]))
            ->body(collect([
                $skipped ? __('admin.academic_student.success.bulk_skipped', ['count' => $skipped]) : null,
                $withoutAccount ? __('admin.academic_student.success.bulk_accounts', ['count' => $withoutAccount]) : null,
            ])->filter()->implode(' ') ?: null)
            ->status($enrolled === 0 ? 'danger' : ($skipped || $withoutAccount ? 'warning' : 'success'))
            ->send();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAcademicStudents::route('/'),
            'view' => Pages\ViewAcademicStudent::route('/{record}'),
        ];
    }
}
