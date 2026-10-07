<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\Concerns\MasterData;
use App\Filament\Admin\Resources\CountryResource\Pages;
use App\Models\Country;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

/**
 * Master negara ISO 3166-1: hanya dibaca dan dinonaktifkan. Daftarnya berasal dari
 * CountrySeeder, jadi tidak ada halaman tambah/ubah — cukup sakelar "aktif" di tabel.
 */
class CountryResource extends Resource
{
    use MasterData;

    protected static ?string $model = Country::class;

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationIcon = 'lucide-globe';

    protected static ?string $recordTitleAttribute = 'name_en';

    public static function getModelLabel(): string
    {
        return __('admin.country.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.country.plural');
    }

    public static function masterDescription(): string
    {
        return __('admin.country.description');
    }

    public static function table(Table $table): Table
    {
        return static::masterTable($table)
            ->searchPlaceholder(__('admin.common.search', ['label' => Str::lower(__('admin.country.plural'))]))
            ->defaultSort(fn (): string => app()->getLocale() === 'id' ? 'name_id' : 'name_en')
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label(__('admin.country.fields.code'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('name_en')
                    ->label(__('admin.country.fields.name_en'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('name_id')
                    ->label(__('admin.country.fields.name_id'))
                    ->searchable()
                    ->sortable(),

                static::activeColumn(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('admin.common.is_active')),
            ])
            ->actions([])
            ->bulkActions([]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageCountries::route('/'),
        ];
    }
}
