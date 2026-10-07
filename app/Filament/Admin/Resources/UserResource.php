<?php

namespace App\Filament\Admin\Resources;

use App\Enums\UserRole;
use App\Filament\Admin\Resources\Concerns\MasterData;
use App\Filament\Admin\Resources\UserResource\Pages;
use App\Models\Faculty;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * UC-23 Kelola admin: hanya Super Admin (UserPolicy). Resource ini sengaja dibatasi pada akun
 * staf universitas; akun agen & mahasiswa dikelola lewat resource-nya masing-masing agar
 * peran tidak bisa dinaikkan dari sini.
 */
class UserResource extends Resource
{
    use MasterData;

    protected static ?string $model = User::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 6;

    protected static ?string $navigationIcon = 'lucide-user-cog';

    public static function getModelLabel(): string
    {
        return __('admin.user.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.user.plural');
    }

    public static function masterDescription(): string
    {
        return __('admin.user.description');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]) // filter "dihapus" mengaturnya sendiri
            ->whereIn('role', UserRole::adminRoles());
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Grid::make(2)
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label(__('admin.user.fields.name'))
                        ->required()
                        ->maxLength(255),

                    Forms\Components\TextInput::make('email')
                        ->label(__('admin.user.fields.email'))
                        ->email()
                        ->required()
                        ->maxLength(255)
                        ->unique(ignoreRecord: true)
                        ->dehydrateStateUsing(fn (string $state): string => mb_strtolower(trim($state))),

                    Forms\Components\Select::make('role')
                        ->label(__('admin.user.fields.role'))
                        ->options(fn (): array => collect(UserRole::adminRoles())
                            ->mapWithKeys(fn (UserRole $role): array => [$role->value => $role->getLabel()])
                            ->all())
                        ->default(UserRole::Admin->value)
                        ->required()
                        ->live(),

                    Forms\Components\Select::make('faculty_id')
                        ->label(__('admin.user.fields.faculty'))
                        ->helperText(__('admin.user.fields.faculty_hint'))
                        ->options(fn (): array => Faculty::query()->orderBy('name')->pluck('name', 'id')->all())
                        ->searchable()
                        ->preload()
                        // Fakultas hanya berlaku untuk admin fakultas; super admin melihat semuanya.
                        ->visible(fn (Get $get): bool => $get('role') === UserRole::Admin->value)
                        ->required(fn (Get $get): bool => $get('role') === UserRole::Admin->value)
                        ->validationMessages([
                            'required' => __('admin.user.validation.faculty_required'),
                        ])
                        ->dehydrateStateUsing(fn (Get $get, ?string $state): ?string => $get('role') === UserRole::Admin->value ? $state : null),

                    Forms\Components\TextInput::make('password')
                        ->label(__('admin.user.fields.password'))
                        ->password()
                        ->revealable()
                        ->minLength(8)
                        ->required(fn (string $operation): bool => $operation === 'create')
                        ->helperText(fn (string $operation): ?string => $operation === 'edit' ? __('admin.user.fields.password_hint_edit') : null)
                        ->dehydrated(fn (?string $state): bool => filled($state))
                        ->dehydrateStateUsing(fn (string $state): string => Hash::make($state)),

                    static::activeField()
                        ->label(__('admin.user.fields.is_active'))
                        // Mencegah super admin mengunci dirinya sendiri dari panel.
                        ->disabled(fn (?User $record): bool => $record?->is(Filament::auth()->user()) ?? false)
                        ->helperText(fn (?User $record): string => $record?->is(Filament::auth()->user())
                            ? __('admin.user.yourself')
                            : __('admin.user.active_hint')),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return static::masterTable($table)
            ->defaultSort('name')
            ->searchPlaceholder(__('admin.common.search', ['label' => Str::lower(__('admin.user.plural'))]))
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('admin.user.fields.name'))
                    ->description(fn (User $record): string => $record->email)
                    ->searchable(['name', 'email'])
                    ->sortable(),

                Tables\Columns\TextColumn::make('role')
                    ->label(__('admin.user.fields.role'))
                    ->badge()
                    ->icon(fn (UserRole $state): string => $state === UserRole::SuperAdmin
                        ? 'lucide-shield-check'
                        : 'lucide-landmark')
                    ->color(fn (UserRole $state): string => $state === UserRole::SuperAdmin ? 'primary' : 'gray')
                    ->sortable(),

                Tables\Columns\TextColumn::make('faculty.name')
                    ->label(__('admin.user.fields.faculty'))
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('email_verified_at')
                    ->label(__('admin.user.fields.email_verified_at'))
                    ->dateTime()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                // Akun sendiri tidak bisa dinonaktifkan dari panel.
                static::activeColumn()
                    ->disabled(fn (User $record): bool => $record->is(Filament::auth()->user()) || ! static::canEdit($record)),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('role')
                    ->label(__('admin.user.fields.role'))
                    ->options(fn (): array => collect(UserRole::adminRoles())
                        ->mapWithKeys(fn (UserRole $role): array => [$role->value => $role->getLabel()])
                        ->all()),

                Tables\Filters\SelectFilter::make('faculty_id')
                    ->label(__('admin.user.fields.faculty'))
                    ->relationship('faculty', 'name')
                    ->searchable(),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('admin.user.fields.is_active')),

                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                static::editAction(),
                // Akun sendiri tidak bisa dihapus dari panel; alasannya ditulis di tooltip.
                static::deleteAction()
                    ->disabled(fn (User $record): bool => $record->is(Filament::auth()->user()))
                    ->tooltip(fn (User $record): string => $record->is(Filament::auth()->user())
                        ? __('admin.user.yourself')
                        : __('admin.master.delete')),
                static::restoreAction(),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageUsers::route('/'),
        ];
    }
}
