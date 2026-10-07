<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\Concerns\MasterData;
use App\Filament\Admin\Resources\FacultyResource\Pages;
use App\Models\Faculty;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * UC-22 Kelola fakultas. Akses dibatasi FacultyPolicy (hanya Super Admin).
 */
class FacultyResource extends Resource
{
    use MasterData;

    protected static ?string $model = Faculty::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationIcon = 'lucide-landmark';

    public static function getModelLabel(): string
    {
        return __('admin.faculty.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.faculty.plural');
    }

    public static function masterDescription(): string
    {
        return __('admin.faculty.hint');
    }

    /**
     * programs_count dipakai kolom tabel sekaligus penentu apakah Hapus boleh dipakai,
     * jadi dimuat di sini agar tetap ada walau kolomnya disembunyikan.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withCount('programs');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Grid::make(2)
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label(__('admin.faculty.fields.name'))
                        ->required()
                        ->maxLength(255),

                    Forms\Components\TextInput::make('code')
                        ->label(__('admin.faculty.fields.code'))
                        ->helperText(__('admin.faculty.fields.code_hint'))
                        ->required()
                        ->maxLength(20)
                        ->unique(ignoreRecord: true)
                        // Kode dipakai sebagai kunci seeder, jadi selalu disimpan huruf besar.
                        ->dehydrateStateUsing(fn (string $state): string => mb_strtoupper(trim($state))),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return static::masterTable($table)
            ->defaultSort('name')
            ->searchPlaceholder(__('admin.common.search', ['label' => Str::lower(__('admin.faculty.plural'))]))
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('admin.faculty.fields.name'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('code')
                    ->label(__('admin.faculty.fields.code'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('programs_count')
                    ->label(__('admin.faculty.fields.programs_count'))
                    ->counts('programs')
                    ->sortable(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label(__('admin.common.updated_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([
                static::editAction(),
                // Fakultas yang masih punya program ditolak database (FK). Tombolnya tetap
                // ditampilkan tapi mati, supaya alasannya terbaca.
                static::deleteAction()
                    ->disabled(fn (Faculty $record): bool => ($record->programs_count ?? 0) > 0)
                    ->tooltip(fn (Faculty $record): string => ($record->programs_count ?? 0) > 0
                        ? __('admin.faculty.delete_blocked')
                        : __('admin.master.delete')),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageFaculties::route('/'),
        ];
    }
}
