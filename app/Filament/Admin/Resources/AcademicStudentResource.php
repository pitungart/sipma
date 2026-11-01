<?php

namespace App\Filament\Admin\Resources;

use App\Enums\StudentStatus;
use App\Enums\UserRole;
use App\Filament\Admin\Resources\AcademicStudentResource\Pages;
use App\Filament\Admin\Resources\Concerns\AcademicModule;
use App\Models\Country;
use App\Models\Faculty;
use App\Models\Program;
use App\Models\Student;
use App\Support\EnrollmentPeriod;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
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
            ->with(['program.faculty', 'nationality', 'agent:id,company_name']);
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
            // Detail khusus Mahasiswa Asing menyusul (TP-1 F2); sementara ke detail pendaftaran
            ->recordUrl(fn (Student $record): string => StudentResource::getUrl('view', ['record' => $record]))
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
                Tables\Actions\ViewAction::make()
                    ->iconButton()
                    ->icon('lucide-eye')
                    ->color('gray')
                    ->tooltip(__('admin.applicant.open'))
                    ->url(fn (Student $record): string => StudentResource::getUrl('view', ['record' => $record])),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAcademicStudents::route('/'),
        ];
    }
}
