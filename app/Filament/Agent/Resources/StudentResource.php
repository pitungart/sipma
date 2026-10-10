<?php

namespace App\Filament\Agent\Resources;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\StudentStatus;
use App\Filament\Agent\Resources\StudentResource\Pages;
use App\Filament\Support\ApplicantForm;
use App\Models\AcademicPeriod;
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
 * UC-14 Kelola mahasiswa (agen): "Mahasiswa saya". Baris dibatasi Student::scopeVisibleTo
 * (hanya mahasiswa yang didaftarkan agen ini), menu terbuka setelah MOU disetujui (BPMN S3).
 * Program hanya dari MOU agen (catatan UC-04); periode dipilih otomatis.
 */
class StudentResource extends Resource
{
    protected static ?string $model = Student::class;

    protected static ?string $slug = 'students';

    protected static ?string $navigationIcon = 'lucide-users';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'full_name';

    public static function getModelLabel(): string
    {
        return __('agent.students.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('agent.students.plural');
    }

    public static function getNavigationLabel(): string
    {
        return static::getPluralModelLabel();
    }

    /**
     * Gerbang onboarding: sebelum MOU disetujui menu tidak tampil dan URL-nya 403;
     * kartu terkunci di dasbor menjelaskan alasannya.
     */
    public static function canAccess(): bool
    {
        return parent::canAccess()
            && (Filament::auth()->user()?->agent?->canRegisterStudents() ?? false);
    }

    /**
     * Jumlah pendaftaran yang diminta revisi KUI (perlu tindakan agen).
     */
    public static function getNavigationBadge(): ?string
    {
        $count = static::getEloquentQuery()->where('status', StudentStatus::Revision)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->visibleTo(Filament::auth()->user())
            ->with(['program', 'nationality']) // program lengkap: nominal biaya dipakai di detail
            ->withCount(['documents as required_documents_count' => fn (Builder $q) => $q
                ->whereIn('type', DocumentType::required())
                ->whereIn('status', [DocumentStatus::Pending, DocumentStatus::Approved])]);
    }

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
     * Program dari MOU agen; terisi otomatis bila MOU hanya mencakup satu program. Program lama
     * pendaftar tetap tersedia saat diubah walau sudah tidak dicakup MOU.
     *
     * @return list<Forms\Components\Component>
     */
    public static function programFields(): array
    {
        $options = function (?Student $record): array {
            $programs = Filament::auth()->user()?->agent?->allowedPrograms()->pluck('name', 'id')->all() ?? [];

            if ($record?->program && ! isset($programs[$record->program_id])) {
                $programs[$record->program_id] = $record->program->name;
            }

            return $programs;
        };

        return [
            ApplicantForm::programSelect($options)
                ->default(function () use ($options): ?string {
                    $programs = $options(null);

                    return count($programs) === 1 ? (string) array_key_first($programs) : null;
                })
                ->live()
                ->helperText(function (Forms\Get $get): string {
                    $period = AcademicPeriod::currentFor($get('program_id'));

                    return match (true) {
                        blank($get('program_id')) => __('agent.students.program_hint'),
                        $period === null => __('agent.students.no_open_period'),
                        default => __('agent.students.open_period', [
                            'period' => $period->name,
                            'date' => $period->registration_closes_at->translatedFormat('j F Y'),
                        ]),
                    };
                })
                ->columnSpanFull(),
        ];
    }

    /**
     * Periode mengikuti program: periode yang sedang dibuka (agen tidak memilih periode).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function withCurrentPeriod(array $data, ?Student $record = null): array
    {
        if ($record === null || $record->program_id !== ($data['program_id'] ?? null) || $record->academic_period_id === null) {
            $data['academic_period_id'] = AcademicPeriod::currentFor($data['program_id'] ?? null)?->getKey();
        }

        return $data;
    }

    public static function table(Table $table): Table
    {
        $required = count(DocumentType::required());

        return $table
            ->defaultSort('updated_at', 'desc')
            ->searchPlaceholder(__('admin.common.search', ['label' => Str::lower(__('agent.students.plural'))]))
            ->recordUrl(fn (Student $record): string => static::getUrl('view', ['record' => $record]))
            ->paginationPageOptions([10, 25, 50, 100])
            ->defaultPaginationPageOption(10)
            ->emptyStateIcon('lucide-users')
            ->emptyStateHeading(__('agent.students.empty_heading'))
            ->emptyStateDescription(__('agent.students.empty_description'))
            ->emptyStateActions([
                Tables\Actions\Action::make('create')
                    ->label(__('agent.students.actions.create'))
                    ->icon('lucide-plus')
                    ->url(fn (): string => static::getUrl('create')),
            ])
            ->columns([
                Tables\Columns\TextColumn::make('full_name')
                    ->label(__('admin.dashboard.applicant_name'))
                    ->description(fn (Student $record): string => collect([$record->registration_number ?? $record->passport_number, $record->nationality?->name])->filter()->implode(' · '))
                    ->searchable(['full_name', 'email', 'passport_number', 'registration_number'])
                    ->sortable(),

                Tables\Columns\TextColumn::make('program.name')
                    ->label(__('admin.program.label'))
                    ->wrap()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('required_documents_count')
                    ->label(__('admin.applicant.fields.documents'))
                    ->formatStateUsing(fn (int $state): string => "{$state}/{$required}")
                    ->color(fn (int $state): string => $state === $required ? 'success' : 'gray')
                    ->tooltip(__('agent.students.documents_hint')),

                Tables\Columns\TextColumn::make('status')
                    ->label(__('admin.dashboard.status'))
                    ->badge(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label(__('agent.students.updated_at'))
                    ->since()
                    ->sortable(),
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
            'index' => Pages\ListStudents::route('/'),
            'create' => Pages\CreateStudent::route('/create'),
            'view' => Pages\ViewStudent::route('/{record}'),
            'edit' => Pages\EditStudent::route('/{record}/edit'),
        ];
    }
}
