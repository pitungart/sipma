<?php

namespace App\Filament\Admin\Resources;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\StudentStatus;
use App\Filament\Admin\Resources\StudentResource\Pages;
use App\Models\AcademicPeriod;
use App\Models\Program;
use App\Models\Student;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * UC-15 Kelola pendaftaran (Super Admin) dan UC-28 Lihat mahasiswa di programnya (Admin Fakultas,
 * read-only). Pembatasan baris memakai Student::scopeVisibleTo; aksi verifikasi hanya untuk
 * pemegang ability `verify` (Super Admin via Gate::before).
 */
class StudentResource extends Resource
{
    protected static ?string $model = Student::class;

    protected static ?string $slug = 'applicants';

    protected static ?string $navigationIcon = 'lucide-users';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'full_name';

    public static function getNavigationGroup(): ?string
    {
        return __('admin.groups.admissions');
    }

    public static function getModelLabel(): string
    {
        return __('admin.applicant.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.applicant.plural');
    }

    public static function getNavigationLabel(): string
    {
        return static::getPluralModelLabel();
    }

    /**
     * Jumlah pendaftaran yang menunggu tindakan KUI (diajukan), seperti angka di menu template.
     */
    public static function getNavigationBadge(): ?string
    {
        $count = static::getEloquentQuery()->where('status', StudentStatus::Submitted)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->visibleTo(Filament::auth()->user())
            ->with(['program:id,name,faculty_id', 'nationality', 'agent:id,company_name'])
            ->withCount(['documents as approved_documents_count' => fn (Builder $q) => $q
                ->where('status', DocumentStatus::Approved)
                ->whereIn('type', DocumentType::required())]);
    }

    public static function canCreate(): bool
    {
        return false; // pendaftar mendaftar sendiri atau lewat agen
    }

    public static function table(Table $table): Table
    {
        $required = count(DocumentType::required());

        return $table
            ->defaultSort('created_at', 'desc')
            ->searchPlaceholder(__('admin.common.search', ['label' => Str::lower(__('admin.applicant.plural'))]))
            ->recordUrl(fn (Student $record): string => static::getUrl('view', ['record' => $record]))
            ->paginationPageOptions([10, 25, 50, 100])
            ->defaultPaginationPageOption(10)
            ->emptyStateIcon('lucide-users')
            ->emptyStateHeading(__('admin.applicant.empty_heading'))
            ->emptyStateDescription(__('admin.applicant.empty_description'))
            ->columns([
                Tables\Columns\TextColumn::make('full_name')
                    ->label(__('admin.dashboard.applicant_name'))
                    ->description(fn (Student $record): string => collect([$record->passport_number, $record->nationality?->name])->filter()->implode(' · '))
                    ->searchable(['full_name', 'email', 'passport_number'])
                    ->sortable(),

                Tables\Columns\TextColumn::make('program.name')
                    ->label(__('admin.program.label'))
                    ->wrap()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('agent.company_name')
                    ->label(__('admin.dashboard.source'))
                    ->placeholder(__('admin.dashboard.source_self'))
                    ->toggleable(),

                Tables\Columns\TextColumn::make('approved_documents_count')
                    ->label(__('admin.applicant.fields.documents'))
                    ->formatStateUsing(fn (int $state): string => "{$state}/{$required}")
                    ->color(fn (int $state): string => $state === $required ? 'success' : 'gray')
                    ->tooltip(__('admin.applicant.documents_hint')),

                Tables\Columns\TextColumn::make('status')
                    ->label(__('admin.dashboard.status'))
                    ->badge(),

                Tables\Columns\TextColumn::make('submitted_at')
                    ->label(__('admin.applicant.fields.submitted_at'))
                    ->date('j M Y')
                    ->placeholder('—')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('admin.dashboard.registered_at'))
                    ->date('j M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('program_id')
                    ->label(__('admin.program.label'))
                    ->options(fn (): array => Program::query()->visibleTo(Filament::auth()->user())->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable(),

                Tables\Filters\SelectFilter::make('academic_period_id')
                    ->label(__('admin.period.label'))
                    ->options(fn (): array => AcademicPeriod::query()->visibleTo(Filament::auth()->user())->orderByDesc('registration_opens_at')->pluck('name', 'id')->all())
                    ->searchable(),

                Tables\Filters\SelectFilter::make('source')
                    ->label(__('admin.dashboard.source'))
                    ->options([
                        'agent' => __('admin.dashboard.source_agent'),
                        'self' => __('admin.dashboard.source_self'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        'agent' => $query->whereNotNull('agent_id'),
                        'self' => $query->whereNull('agent_id'),
                        default => $query,
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->iconButton()
                    ->icon('lucide-eye')
                    ->color('gray')
                    ->tooltip(__('admin.applicant.open')),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListApplicants::route('/'),
            'view' => Pages\ViewApplicant::route('/{record}'),
        ];
    }
}
