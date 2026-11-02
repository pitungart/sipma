<?php

namespace App\Filament\Admin\Resources;

use App\Enums\UserRole;
use App\Filament\Admin\Resources\Concerns\AcademicModule;
use App\Filament\Admin\Resources\StudentCardResource\Pages;
use App\Filament\Support\StudentCardActions;
use App\Models\Faculty;
use App\Models\Program;
use App\Models\StudentCard;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * TP-2 KTM digital: seluruh KTM lintas mahasiswa. Super Admin menerbitkan dan mengelola;
 * Admin Fakultas hanya melihat KTM mahasiswa fakultasnya. Baris membuka tab KTM di detail mahasiswa.
 */
class StudentCardResource extends Resource
{
    use AcademicModule;

    protected static ?string $model = StudentCard::class;

    protected static ?string $slug = 'student-cards';

    protected static ?string $navigationIcon = 'lucide-id-card';

    protected static ?int $navigationSort = 2;

    public static function getModelLabel(): string
    {
        return __('admin.student_card.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.student_card.plural');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHas('student', fn (Builder $student) => $student->visibleTo(Filament::auth()->user()))
            ->with(['student.program.faculty', 'student.nationality', 'issuer:id,name']);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->searchPlaceholder(__('admin.common.search', ['label' => __('admin.student_card.search')]))
            ->recordUrl(fn (StudentCard $record): string => AcademicStudentResource::getUrl('view', ['record' => $record->student_id, 'tab' => 'card']))
            ->paginationPageOptions([10, 25, 50, 100])
            ->defaultPaginationPageOption(10)
            ->emptyStateIcon('lucide-id-card')
            ->emptyStateHeading(__('admin.student_card.empty_heading'))
            ->emptyStateDescription(__('admin.student_card.empty_description'))
            ->columns([
                Tables\Columns\ViewColumn::make('holder')
                    ->label(__('admin.student_card.fields.holder'))
                    ->view('filament.admin.columns.card-holder')
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas('student', fn (Builder $student) => $student
                        ->where('full_name', 'like', "%{$search}%")
                        ->orWhere('passport_number', 'like', "%{$search}%")
                        ->orWhere('registration_number', 'like', "%{$search}%"))),

                Tables\Columns\TextColumn::make('card_number')
                    ->label(__('admin.student_card.fields.card_number'))
                    ->fontFamily('mono')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('student.program.name')
                    ->label(__('admin.program.label'))
                    ->description(fn (StudentCard $record): ?string => $record->student?->program?->faculty?->name)
                    ->wrap(),

                Tables\Columns\TextColumn::make('issued_at')
                    ->label(__('admin.student_card.fields.issued_at'))
                    ->date('j M Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('valid_until')
                    ->label(__('admin.student_card.fields.valid_until'))
                    ->date('j M Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label(__('admin.student_card.fields.status'))
                    ->badge(),

                Tables\Columns\TextColumn::make('issuer.name')
                    ->label(__('admin.student_card.fields.issued_by'))
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('faculty')
                    ->label(__('admin.academic_student.fields.faculty'))
                    ->visible(fn (): bool => Filament::auth()->user()?->hasRole(UserRole::SuperAdmin) === true)
                    ->options(fn (): array => Faculty::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->whereHas('student.program', fn (Builder $program) => $program->where('faculty_id', $data['value']))
                        : $query),

                Tables\Filters\SelectFilter::make('program')
                    ->label(__('admin.program.label'))
                    ->options(fn (): array => Program::query()->visibleTo(Filament::auth()->user())->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->whereHas('student', fn (Builder $student) => $student->where('program_id', $data['value']))
                        : $query),
            ])
            ->actions([
                Tables\Actions\Action::make('download')
                    ->iconButton()
                    ->icon('lucide-download')
                    ->color('gray')
                    ->tooltip(__('admin.student_card.actions.download'))
                    ->visible(fn (StudentCard $record): bool => Gate::allows('download', $record))
                    ->url(fn (StudentCard $record): string => route('files.student-card', ['card' => $record, 'download' => 1])),

                StudentCardActions::reissue(Tables\Actions\Action::make('reissue'))
                    ->iconButton()
                    ->tooltip(__('admin.student_card.actions.reissue')),

                StudentCardActions::revoke(Tables\Actions\Action::make('revoke'))
                    ->iconButton()
                    ->tooltip(__('admin.student_card.actions.revoke')),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStudentCards::route('/'),
        ];
    }
}
