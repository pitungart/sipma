<?php

namespace App\Filament\Admin\Resources;

use App\Enums\UserRole;
use App\Filament\Admin\Resources\Concerns\MasterData;
use App\Filament\Admin\Resources\ProgramResource\Pages;
use App\Models\Faculty;
use App\Models\Program;
use App\Models\User;
use App\Support\Rupiah;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule as ValidationRule;
use Illuminate\Validation\Rules\In;

/**
 * UC-27 Kelola program. Super Admin melihat semua; Admin Fakultas hanya program fakultasnya
 * (ProgramPolicy + Program::scopeVisibleTo).
 */
class ProgramResource extends Resource
{
    use MasterData;

    protected static ?string $model = Program::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationIcon = 'lucide-graduation-cap';

    public static function getModelLabel(): string
    {
        return __('admin.program.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.program.plural');
    }

    public static function masterDescription(): string
    {
        return __('admin.program.description');
    }

    public static function modalWidth(): MaxWidth
    {
        return MaxWidth::TwoExtraLarge;
    }

    /**
     * Admin Fakultas tidak pernah melihat program fakultas lain, termasuk lewat URL langsung.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]) // filter "dihapus" mengaturnya sendiri
            ->visibleTo(Filament::auth()->user());
    }

    public static function form(Form $form): Form
    {
        $user = Filament::auth()->user();

        return $form->schema([
            Forms\Components\Section::make(__('admin.program.sections.identity'))
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('faculty_id')
                        ->label(__('admin.program.fields.faculty'))
                        ->options(fn (): array => self::facultyOptions($user))
                        // Admin Fakultas tidak boleh memindahkan program ke fakultas lain.
                        ->default(fn (): ?string => $user?->faculty_id)
                        ->disabled(fn (): bool => $user?->hasRole(UserRole::Admin) ?? false)
                        ->dehydrated()
                        ->required()
                        ->rule(fn (): In => ValidationRule::in(array_keys(self::facultyOptions($user))))
                        ->searchable()
                        ->preload(),

                    Forms\Components\TextInput::make('code')
                        ->label(__('admin.program.fields.code'))
                        ->required()
                        ->maxLength(20)
                        ->unique(ignoreRecord: true)
                        ->dehydrateStateUsing(fn (string $state): string => mb_strtoupper(trim($state))),

                    Forms\Components\TextInput::make('name')
                        ->label(__('admin.program.fields.name'))
                        ->required()
                        ->maxLength(255)
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (Forms\Set $set, Get $get, ?string $state): void {
                            if (blank($get('slug')) && filled($state)) {
                                $set('slug', Str::slug($state));
                            }
                        }),

                    Forms\Components\TextInput::make('slug')
                        ->label(__('admin.program.fields.slug'))
                        ->helperText(__('admin.program.fields.slug_hint'))
                        ->maxLength(255)
                        ->unique(ignoreRecord: true),

                    Forms\Components\Textarea::make('description')
                        ->label(__('admin.program.fields.description'))
                        ->rows(3)
                        ->columnSpanFull(),

                ]),

            Forms\Components\Section::make(__('admin.program.sections.fees'))
                ->description(__('admin.program.fields.fee_hint'))
                ->columns(2)
                ->schema([
                    self::feeField('admission_fee', __('admin.program.fields.admission_fee')),
                    self::feeField('tuition_fee', __('admin.program.fields.tuition_fee')),
                ]),

            static::activeField(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return static::masterTable($table)
            ->defaultSort('name')
            ->searchPlaceholder(__('admin.common.search', ['label' => Str::lower(__('admin.program.plural'))]))
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('admin.program.fields.name'))
                    ->description(fn (Program $record): string => $record->code)
                    ->searchable(['name', 'code'])
                    ->sortable(),

                Tables\Columns\TextColumn::make('faculty.code')
                    ->label(__('admin.program.fields.faculty'))
                    ->tooltip(fn (Program $record): ?string => $record->faculty?->name)
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('admission_fee')
                    ->label(__('admin.program.fields.admission_fee'))
                    ->formatStateUsing(fn (string $state): string => Rupiah::format($state))
                    ->sortable(),

                Tables\Columns\TextColumn::make('tuition_fee')
                    ->label(__('admin.program.fields.tuition_fee'))
                    ->formatStateUsing(fn (string $state): string => Rupiah::format($state))
                    ->sortable(),

                Tables\Columns\TextColumn::make('academic_periods_count')
                    ->label(__('admin.program.fields.periods_count'))
                    ->counts('academicPeriods')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('students_count')
                    ->label(__('admin.program.fields.students_count'))
                    ->counts('students')
                    ->toggleable(),

                static::activeColumn(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('faculty_id')
                    ->label(__('admin.program.fields.faculty'))
                    ->options(fn (): array => self::facultyOptions(Filament::auth()->user()))
                    ->visible(fn (): bool => Filament::auth()->user()?->hasRole(UserRole::SuperAdmin) ?? false),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('admin.common.is_active')),

                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                static::editAction(),
                static::deleteAction(),
                static::restoreAction(),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManagePrograms::route('/'),
        ];
    }

    /**
     * Semua nominal rupiah (tanpa desimal di input agar mudah dibaca).
     */
    private static function feeField(string $name, string $label): Forms\Components\TextInput
    {
        return Forms\Components\TextInput::make($name)
            ->label($label)
            ->prefix('Rp')
            ->numeric()
            ->minValue(0)
            ->step(1000)
            ->default(0)
            ->required();
    }

    /**
     * Admin Fakultas hanya boleh memilih fakultasnya sendiri.
     *
     * @return array<string, string>
     */
    private static function facultyOptions(?User $user): array
    {
        $query = Faculty::query()->orderBy('name');

        if ($user?->hasRole(UserRole::Admin)) {
            $query->whereKey($user->faculty_id);
        }

        return $query->pluck('name', 'id')->all();
    }
}
