<?php

namespace App\Filament\Admin\Resources;

use App\Enums\UserRole;
use App\Filament\Admin\Resources\AcademicPeriodResource\Pages;
use App\Filament\Admin\Resources\Concerns\MasterData;
use App\Models\AcademicPeriod;
use App\Models\Program;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule as ValidationRule;
use Illuminate\Validation\Rules\In;

/**
 * Periode (intake) pendaftaran tiap program. Kepemilikannya mengikuti program
 * (AcademicPeriodPolicy + AcademicPeriod::scopeVisibleTo).
 */
class AcademicPeriodResource extends Resource
{
    use MasterData;

    protected static ?string $model = AcademicPeriod::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationIcon = 'lucide-calendar-range';

    public static function getModelLabel(): string
    {
        return __('admin.period.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.period.plural');
    }

    public static function masterDescription(): string
    {
        return __('admin.period.description');
    }

    public static function modalWidth(): MaxWidth
    {
        return MaxWidth::TwoExtraLarge;
    }

    public static function getEloquentQuery(): Builder
    {
        $user = Filament::auth()->user();

        // Staf melihat seluruh periode program yang ia kelola, termasuk yang sudah lewat;
        // scopeVisibleTo membatasi agen & mahasiswa ke periode yang sedang dibuka.
        return parent::getEloquentQuery()
            ->visibleTo($user)
            ->withCount('students'); // penentu apakah Hapus boleh dipakai
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('admin.period.sections.registration'))
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('program_id')
                        ->label(__('admin.period.fields.program'))
                        ->options(fn (): array => self::programOptions(Filament::auth()->user()))
                        ->required()
                        ->rule(fn (): In => ValidationRule::in(array_keys(self::programOptions(Filament::auth()->user()))))
                        ->searchable()
                        ->preload()
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('name')
                        ->label(__('admin.period.fields.name'))
                        ->helperText(__('admin.period.fields.name_hint'))
                        ->required()
                        ->maxLength(255),

                    Forms\Components\TextInput::make('code')
                        ->label(__('admin.period.fields.code'))
                        ->required()
                        ->maxLength(30)
                        ->unique(ignoreRecord: true)
                        ->dehydrateStateUsing(fn (string $state): string => mb_strtoupper(trim($state))),

                    Forms\Components\DatePicker::make('registration_opens_at')
                        ->label(__('admin.period.fields.registration_opens_at'))
                        ->required()
                        ->native(false),

                    Forms\Components\DatePicker::make('registration_closes_at')
                        ->label(__('admin.period.fields.registration_closes_at'))
                        ->required()
                        ->native(false)
                        ->afterOrEqual('registration_opens_at')
                        ->validationMessages([
                            'after_or_equal' => __('admin.period.validation.closes_after_opens'),
                        ]),
                ]),

            Forms\Components\Section::make(__('admin.period.sections.schedule'))
                ->columns(2)
                ->schema([
                    Forms\Components\DatePicker::make('starts_on')
                        ->label(__('admin.period.fields.starts_on'))
                        ->native(false),

                    Forms\Components\DatePicker::make('ends_on')
                        ->label(__('admin.period.fields.ends_on'))
                        ->native(false)
                        ->afterOrEqual('starts_on'),

                    Forms\Components\TextInput::make('quota')
                        ->label(__('admin.period.fields.quota'))
                        ->helperText(__('admin.period.fields.quota_hint'))
                        ->numeric()
                        ->minValue(1),
                ]),

            static::activeField(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return static::masterTable($table)
            ->defaultSort('registration_opens_at', 'desc')
            ->searchPlaceholder(__('admin.common.search', ['label' => Str::lower(__('admin.period.plural'))]))
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('admin.period.fields.name'))
                    ->description(fn (AcademicPeriod $record): string => $record->code)
                    ->searchable(['name', 'code'])
                    ->sortable(),

                Tables\Columns\TextColumn::make('program.name')
                    ->label(__('admin.period.fields.program'))
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('registration_opens_at')
                    ->label(__('admin.period.fields.registration_opens_at'))
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('registration_closes_at')
                    ->label(__('admin.period.fields.registration_closes_at'))
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('quota')
                    ->label(__('admin.period.fields.quota'))
                    ->placeholder('—')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('students_count')
                    ->label(__('admin.period.fields.students_count'))
                    ->counts('students')
                    ->toggleable(),

                // R-1.9: status membawa label teks; warna hanya penguat.
                Tables\Columns\TextColumn::make('state')
                    ->label(__('admin.period.fields.state'))
                    ->badge()
                    ->state(fn (AcademicPeriod $record): string => self::state($record))
                    ->formatStateUsing(fn (string $state): string => __("admin.period.state.{$state}"))
                    ->icon(fn (string $state): string => match ($state) {
                        'open' => 'lucide-circle-check',
                        'upcoming' => 'lucide-clock',
                        default => 'lucide-circle-minus',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'open' => 'success',
                        'upcoming' => 'info',
                        default => 'gray',
                    }),

                static::activeColumn(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('program_id')
                    ->label(__('admin.period.fields.program'))
                    ->options(fn (): array => self::programOptions(Filament::auth()->user()))
                    ->searchable(),

                Tables\Filters\Filter::make('open')
                    ->label(__('admin.period.filters.open'))
                    ->query(fn (Builder $query): Builder => $query->open()),
            ])
            ->actions([
                static::editAction(),
                // Periode yang sudah dipakai pendaftar tidak dihapus, cukup dinonaktifkan.
                static::deleteAction()
                    ->disabled(fn (AcademicPeriod $record): bool => ($record->students_count ?? 0) > 0)
                    ->tooltip(fn (AcademicPeriod $record): string => ($record->students_count ?? 0) > 0
                        ? __('admin.period.delete_blocked')
                        : __('admin.master.delete')),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageAcademicPeriods::route('/'),
        ];
    }

    /**
     * Keadaan periode untuk kolom status.
     */
    private static function state(AcademicPeriod $record): string
    {
        if (! $record->is_active) {
            return 'inactive';
        }

        if ($record->registration_opens_at?->isFuture()) {
            return 'upcoming';
        }

        // Hari terakhir pendaftaran masih terhitung terbuka, sama seperti scopeOpen().
        return $record->registration_closes_at?->endOfDay()->isPast() ? 'closed' : 'open';
    }

    /**
     * @return array<string, string>
     */
    private static function programOptions(?User $user): array
    {
        $query = Program::query()->orderBy('name');

        if ($user?->hasRole(UserRole::Admin)) {
            $query->where('faculty_id', $user->faculty_id);
        }

        return $query->pluck('name', 'id')->all();
    }
}
