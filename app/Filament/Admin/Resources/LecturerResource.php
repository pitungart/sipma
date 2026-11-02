<?php

namespace App\Filament\Admin\Resources;

use App\Enums\UserRole;
use App\Filament\Admin\Resources\Concerns\MasterData;
use App\Filament\Admin\Resources\LecturerResource\Pages;
use App\Models\Faculty;
use App\Models\Lecturer;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule as ValidationRule;
use Illuminate\Validation\Rules\In;

/**
 * TP-4 Master Dosen. Super Admin melihat semua; Admin Fakultas hanya dosen fakultasnya.
 */
class LecturerResource extends Resource
{
    use MasterData;

    protected static ?string $model = Lecturer::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationIcon = 'lucide-user-round';

    public static function getModelLabel(): string
    {
        return __('admin.lecturer.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.lecturer.plural');
    }

    public static function masterDescription(): string
    {
        return __('admin.lecturer.description');
    }

    /**
     * Admin Fakultas tidak pernah melihat dosen fakultas lain, termasuk lewat URL langsung.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]) // filter "dihapus" mengaturnya sendiri
            ->visibleTo(Filament::auth()->user())
            ->with('faculty');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->label(__('admin.lecturer.fields.name'))
                ->helperText(__('admin.lecturer.fields.name_hint'))
                ->required()
                ->maxLength(255),

            // Admin Fakultas tidak boleh memindahkan dosen ke fakultas lain
            Forms\Components\Select::make('faculty_id')
                ->label(__('admin.lecturer.fields.faculty'))
                ->options(fn (): array => self::facultyOptions())
                ->default(fn (): ?string => Filament::auth()->user()?->faculty_id)
                ->disabled(fn (): bool => Filament::auth()->user()?->hasRole(UserRole::Admin) ?? false)
                ->dehydrated()
                ->required()
                ->rule(fn (): In => ValidationRule::in(array_keys(self::facultyOptions())))
                ->searchable(),

            Forms\Components\TextInput::make('nip')
                ->label(__('admin.lecturer.fields.nip'))
                ->helperText(__('admin.lecturer.fields.nip_hint'))
                ->maxLength(30)
                ->unique(ignoreRecord: true)
                ->validationMessages(['unique' => __('admin.lecturer.nip_taken')])
                ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? trim($state) : null),

            Forms\Components\TextInput::make('email')
                ->label(__('admin.lecturer.fields.email'))
                ->email()
                ->maxLength(255),

            Forms\Components\TextInput::make('phone')
                ->label(__('admin.lecturer.fields.phone'))
                ->tel()
                ->maxLength(50),

            static::activeField()->helperText(__('admin.lecturer.fields.active_hint')),
        ]);
    }

    public static function table(Table $table): Table
    {
        return static::masterTable($table)
            ->defaultSort('name')
            ->searchPlaceholder(__('admin.common.search', ['label' => Str::lower(__('admin.lecturer.plural'))]))
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('admin.lecturer.fields.name'))
                    ->description(fn (Lecturer $record): ?string => $record->nip)
                    ->searchable(['name', 'nip', 'email'])
                    ->sortable(),

                Tables\Columns\TextColumn::make('faculty.code')
                    ->label(__('admin.lecturer.fields.faculty'))
                    ->description(fn (Lecturer $record): ?string => $record->faculty?->name)
                    ->wrap()
                    ->extraAttributes(['class' => 'max-w-56'])
                    ->toggleable(),

                Tables\Columns\TextColumn::make('email')
                    ->label(__('admin.lecturer.fields.email'))
                    ->placeholder('—')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('phone')
                    ->label(__('admin.lecturer.fields.phone'))
                    ->placeholder('—')
                    ->toggleable(),

                static::activeColumn(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('faculty_id')
                    ->label(__('admin.lecturer.fields.faculty'))
                    ->options(fn (): array => self::facultyOptions())
                    ->searchable()
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
            'index' => Pages\ManageLecturers::route('/'),
        ];
    }

    /**
     * Admin Fakultas hanya boleh memilih fakultasnya sendiri.
     *
     * @return array<string, string>
     */
    private static function facultyOptions(): array
    {
        $user = Filament::auth()->user();
        $query = Faculty::query()->orderBy('name');

        if ($user?->hasRole(UserRole::Admin)) {
            $query->whereKey($user->faculty_id);
        }

        return $query->pluck('name', 'id')->all();
    }
}
