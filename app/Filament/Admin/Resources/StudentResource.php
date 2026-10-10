<?php

namespace App\Filament\Admin\Resources;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\MouStatus;
use App\Enums\StudentStatus;
use App\Filament\Admin\Resources\StudentResource\Pages;
use App\Filament\Support\ApplicantForm;
use App\Models\AcademicPeriod;
use App\Models\Agent;
use App\Models\Program;
use App\Models\Student;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
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
            ->with(['program', 'nationality', 'agent:id,company_name']) // program lengkap: nominal biaya dipakai di detail & LOA
            ->withCount(['documents as approved_documents_count' => fn (Builder $q) => $q
                ->where('status', DocumentStatus::Approved)
                ->whereIn('type', DocumentType::required())]);
    }

    /**
     * Pendaftaran atas nama pendaftar oleh staf: hanya Super Admin (Gate::before);
     * StudentPolicy::create menolak Admin Fakultas.
     */
    public static function canCreate(): bool
    {
        return static::can('create');
    }

    /**
     * Data pendaftar hanya bisa diubah selama draf atau perlu revisi; setelah diajukan, terkunci.
     */
    public static function canEdit(Model $record): bool
    {
        return $record->status->isEditable() && static::can('update', $record);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('admin.applicant.steps.identity'))->columns(2)->schema(ApplicantForm::identityFields()),
            Forms\Components\Section::make(__('admin.applicant.steps.contact'))->columns(2)->schema(ApplicantForm::contactFields()),
            Forms\Components\Section::make(__('admin.applicant.steps.passport'))->columns(2)->schema(ApplicantForm::passportFields()),
            Forms\Components\Section::make(__('admin.applicant.steps.program'))->columns(2)->schema(static::programFields()),
        ]);
    }

    /**
     * @return list<Forms\Components\Component>
     */
    public static function programFields(): array
    {
        return [
            ApplicantForm::programSelect(fn (): array => Program::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all())
                ->live()
                ->afterStateUpdated(fn (Forms\Set $set) => $set('academic_period_id', null)),
            Forms\Components\Select::make('academic_period_id')
                ->label(__('admin.period.label'))
                ->options(fn (Forms\Get $get): array => AcademicPeriod::query()
                    ->where('program_id', $get('program_id'))
                    ->where('is_active', true)
                    ->orderByDesc('registration_opens_at')
                    ->pluck('name', 'id')
                    ->all())
                ->helperText(__('admin.applicant.period_hint')),
            Forms\Components\Select::make('agent_id')
                ->label(__('admin.dashboard.source'))
                ->options(fn (): array => Agent::query()
                    ->whereHas('mous', fn (Builder $q) => $q->where('status', MouStatus::Approved))
                    ->orderBy('company_name')
                    ->pluck('company_name', 'id')
                    ->all())
                ->placeholder(__('admin.dashboard.source_self'))
                ->helperText(__('admin.applicant.source_hint'))
                ->searchable()
                ->columnSpanFull(),
        ];
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
                    // Nomor pendaftaran (setelah diajukan) seperti ID di template; draf memakai nomor paspor
                    ->description(fn (Student $record): string => collect([$record->registration_number ?? $record->passport_number, $record->nationality?->name])->filter()->implode(' · '))
                    ->searchable(['full_name', 'email', 'passport_number', 'registration_number'])
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
            'create' => Pages\CreateApplicant::route('/create'),
            'view' => Pages\ViewApplicant::route('/{record}'),
            'edit' => Pages\EditApplicant::route('/{record}/edit'),
        ];
    }
}
