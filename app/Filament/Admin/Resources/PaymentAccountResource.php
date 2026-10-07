<?php

namespace App\Filament\Admin\Resources;

use App\Enums\PaymentType;
use App\Filament\Admin\Resources\Concerns\MasterData;
use App\Filament\Admin\Resources\PaymentAccountResource\Pages;
use App\Models\PaymentAccount;
use App\Models\Program;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Rekening/VA tujuan pembayaran. Hanya Super Admin (PaymentAccountPolicy), karena rekening
 * dipegang KUI. Seluruh nominal rupiah, jadi tidak ada pilihan mata uang.
 */
class PaymentAccountResource extends Resource
{
    use MasterData;

    protected static ?string $model = PaymentAccount::class;

    protected static ?string $recordTitleAttribute = 'va_number';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationIcon = 'lucide-wallet';

    public static function getModelLabel(): string
    {
        return __('admin.payment_account.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.payment_account.plural');
    }

    public static function masterDescription(): string
    {
        return __('admin.payment_account.hint');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withCount('payments'); // penentu apakah Hapus boleh dipakai
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Grid::make(2)
                ->schema([
                    Forms\Components\TextInput::make('bank_name')
                        ->label(__('admin.payment_account.fields.bank_name'))
                        ->required()
                        ->maxLength(100),

                    Forms\Components\TextInput::make('account_name')
                        ->label(__('admin.payment_account.fields.account_name'))
                        ->required()
                        ->maxLength(255),

                    Forms\Components\TextInput::make('va_number')
                        ->label(__('admin.payment_account.fields.va_number'))
                        ->required()
                        ->maxLength(50)
                        ->unique(ignoreRecord: true),

                    Forms\Components\Select::make('fee_type')
                        ->label(__('admin.payment_account.fields.fee_type'))
                        ->options(PaymentType::class)
                        ->placeholder(__('admin.common.all_fee_types')),

                    Forms\Components\Select::make('program_id')
                        ->label(__('admin.payment_account.fields.program'))
                        ->helperText(__('admin.payment_account.fields.program_hint'))
                        ->options(fn (): array => Program::query()->orderBy('name')->pluck('name', 'id')->all())
                        ->placeholder(__('admin.common.all_programs'))
                        ->searchable()
                        ->preload(),

                    static::activeField(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return static::masterTable($table)
            ->defaultSort('bank_name')
            ->searchPlaceholder(__('admin.common.search', ['label' => Str::lower(__('admin.payment_account.plural'))]))
            ->columns([
                Tables\Columns\TextColumn::make('va_number')
                    ->label(__('admin.payment_account.fields.va_number'))
                    ->description(fn (PaymentAccount $record): string => $record->bank_name)
                    ->searchable(['va_number', 'bank_name'])
                    ->copyable(),

                Tables\Columns\TextColumn::make('account_name')
                    ->label(__('admin.payment_account.fields.account_name'))
                    ->searchable(),

                Tables\Columns\TextColumn::make('fee_type')
                    ->label(__('admin.payment_account.fields.fee_type'))
                    ->placeholder(__('admin.common.all_fee_types')),

                Tables\Columns\TextColumn::make('program.name')
                    ->label(__('admin.payment_account.fields.program'))
                    ->placeholder(__('admin.common.all_programs'))
                    ->toggleable(),

                Tables\Columns\TextColumn::make('payments_count')
                    ->label(__('admin.payment_account.fields.payments_count'))
                    ->counts('payments')
                    ->toggleable(),

                static::activeColumn(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('admin.common.is_active')),
            ])
            ->actions([
                static::editAction(),
                // Rekening yang sudah dipakai membayar ditolak database (FK restrict).
                static::deleteAction()
                    ->disabled(fn (PaymentAccount $record): bool => ($record->payments_count ?? 0) > 0)
                    ->tooltip(fn (PaymentAccount $record): string => ($record->payments_count ?? 0) > 0
                        ? __('admin.payment_account.delete_blocked')
                        : __('admin.master.delete')),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManagePaymentAccounts::route('/'),
        ];
    }
}
