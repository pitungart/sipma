<?php

namespace App\Filament\Admin\Resources;

use App\Enums\DecreePeriod;
use App\Enums\TeachingRole;
use App\Enums\UserRole;
use App\Filament\Admin\Resources\Concerns\AcademicModule;
use App\Filament\Admin\Resources\Concerns\MasterData;
use App\Filament\Admin\Resources\TeachingDecreeResource\Pages;
use App\Models\Course;
use App\Models\Faculty;
use App\Models\Lecturer;
use App\Models\TeachingAssignment;
use App\Models\TeachingDecree;
use App\Support\UploadLimits;
use Closure;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule as ValidationRule;
use Illuminate\Validation\Rules\In;
use Illuminate\Validation\Rules\Unique;

/**
 * TP-4 SK Mengajar. MK harus dari program fakultas SK. Dosen: Admin Fakultas memilih dari fakultasnya,
 * Super Admin dari fakultas mana pun untuk kasus lintas fakultas. Hanya meminjam aksi hapus & pulihkan
 * dari MasterData; form-nya halaman penuh karena berisi daftar penugasan.
 */
class TeachingDecreeResource extends Resource
{
    use AcademicModule, MasterData {
        AcademicModule::getNavigationGroup insteadof MasterData;
        AcademicModule::getNavigationLabel insteadof MasterData;
    }

    protected static ?string $model = TeachingDecree::class;

    protected static ?string $slug = 'teaching-decrees';

    protected static ?string $recordTitleAttribute = 'decree_number';

    protected static ?string $navigationIcon = 'lucide-file-signature';

    protected static ?int $navigationSort = 4;

    public static function getModelLabel(): string
    {
        return __('admin.teaching_decree.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.teaching_decree.plural');
    }

    public static function masterDescription(): string
    {
        return __('admin.teaching_decree.description');
    }

    public static function showsInMasterTabs(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]) // filter "dihapus" mengaturnya sendiri
            ->visibleTo(Filament::auth()->user())
            ->with(['faculty', 'assignments']);
    }

    /**
     * SK yang dihapus dipulihkan dulu sebelum diubah.
     */
    public static function canEdit(Model $record): bool
    {
        return ! $record->trashed() && Gate::allows('update', $record);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('admin.teaching_decree.sections.decree'))
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('decree_number')
                        ->label(__('admin.teaching_decree.fields.number'))
                        ->required()
                        ->maxLength(100)
                        ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule->where('faculty_id', $get('faculty_id')))
                        ->validationMessages(['unique' => __('admin.teaching_decree.number_taken')]),

                    Forms\Components\DatePicker::make('decree_date')
                        ->label(__('admin.teaching_decree.fields.date'))
                        ->native(false)
                        ->required(),

                    // Ganti fakultas mengosongkan MK karena daftar pilihannya ikut berganti
                    Forms\Components\Select::make('faculty_id')
                        ->label(__('admin.teaching_decree.fields.faculty'))
                        ->options(fn (): array => self::facultyOptions())
                        ->default(fn (): ?string => Filament::auth()->user()?->faculty_id)
                        ->disabled(fn (): bool => Filament::auth()->user()?->hasRole(UserRole::Admin) ?? false)
                        ->dehydrated()
                        ->required()
                        ->rule(fn (): In => ValidationRule::in(array_keys(self::facultyOptions())))
                        ->searchable()
                        ->live()
                        ->afterStateUpdated(fn (Get $get, Set $set) => $set('assignments', collect($get('assignments') ?? [])
                            ->map(fn (array $row): array => [...$row, 'course_id' => null])
                            ->all()))
                        ->columnSpanFull(),

                    Forms\Components\DatePicker::make('period_start')
                        ->label(__('admin.teaching_decree.fields.period_start'))
                        ->native(false)
                        ->required()
                        ->live(),

                    Forms\Components\DatePicker::make('period_end')
                        ->label(__('admin.teaching_decree.fields.period_end'))
                        ->native(false)
                        ->required()
                        ->afterOrEqual('period_start'),

                    Forms\Components\Textarea::make('notes')
                        ->label(__('admin.teaching_decree.fields.notes'))
                        ->rows(3)
                        ->columnSpanFull(),
                ]),

            Forms\Components\Section::make(__('admin.teaching_decree.sections.document'))
                ->description(fn (): string => __('admin.teaching_decree.document_hint', ['max' => UploadLimits::label(UploadLimits::teachingDecree())]))
                ->schema([
                    Forms\Components\Placeholder::make('current_document')
                        ->label(__('admin.teaching_decree.current_document'))
                        ->visible(fn (?TeachingDecree $record): bool => filled($record?->document))
                        ->content(fn (?TeachingDecree $record): HtmlString => new HtmlString(
                            '<a class="sipma-link" target="_blank" href="'.e(route('files.teaching-decree', $record)).'">'.e(__('admin.teaching_decree.open_document')).'</a>',
                        )),

                    // Disimpan halaman setelah SK punya ID, ke disk privat (PrivateFiles)
                    Forms\Components\FileUpload::make('document_file')
                        ->hiddenLabel()
                        ->acceptedFileTypes(['application/pdf'])
                        ->maxSize(fn (): int => UploadLimits::teachingDecree())
                        ->storeFiles(false),
                ]),

            Forms\Components\Section::make(__('admin.teaching_decree.sections.assignments'))
                ->description(__('admin.teaching_decree.assignments_hint'))
                ->schema([
                    Forms\Components\Repeater::make('assignments')
                        ->hiddenLabel()
                        ->relationship()
                        ->columns(['default' => 1, 'md' => 12])
                        ->defaultItems(1)
                        ->minItems(1)
                        ->required()
                        ->reorderable(false)
                        ->addActionLabel(__('admin.teaching_decree.add_assignment'))
                        ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                            $pairs = collect($value)->map(fn (array $row): string => ($row['lecturer_id'] ?? '').'|'.($row['course_id'] ?? ''));

                            if ($pairs->count() !== $pairs->unique()->count()) {
                                $fail(__('admin.teaching_decree.duplicate_assignment'));
                            }
                        })
                        ->schema([
                            Forms\Components\Select::make('lecturer_id')
                                ->label(__('admin.lecturer.label'))
                                ->options(fn (?Model $record): array => self::lecturerOptions($record))
                                ->in(fn (Forms\Components\Select $component): array => array_keys($component->getOptions()))
                                ->searchable()
                                ->required()
                                ->columnSpan(['default' => 1, 'md' => 5]),

                            Forms\Components\Select::make('course_id')
                                ->label(__('admin.course.label'))
                                ->options(fn (Get $get, ?Model $record): array => self::courseOptions($get('../../faculty_id'), $record))
                                ->in(fn (Forms\Components\Select $component): array => array_keys($component->getOptions()))
                                ->searchable()
                                ->required()
                                ->columnSpan(['default' => 1, 'md' => 5]),

                            Forms\Components\Select::make('role')
                                ->label(__('admin.teaching_decree.fields.role'))
                                ->options(TeachingRole::class)
                                ->default(TeachingRole::Lecturer->value)
                                ->rule(ValidationRule::enum(TeachingRole::class))
                                ->required()
                                ->columnSpan(['default' => 1, 'md' => 2]),
                        ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        $download = fn (TeachingDecree $record): string => route('files.teaching-decree', ['decree' => $record, 'download' => 1]);

        return $table
            ->defaultSort('decree_date', 'desc')
            ->searchPlaceholder(__('admin.common.search', ['label' => Str::lower(__('admin.teaching_decree.plural'))]))
            ->paginationPageOptions([10, 25, 50, 100])
            ->defaultPaginationPageOption(10)
            ->emptyStateIcon('lucide-file-signature')
            ->emptyStateHeading(__('admin.teaching_decree.empty_heading'))
            ->emptyStateDescription(__('admin.teaching_decree.empty_description'))
            ->columns([
                Tables\Columns\TextColumn::make('decree_number')
                    ->label(__('admin.teaching_decree.fields.number'))
                    ->description(fn (TeachingDecree $record): ?string => $record->decree_date?->translatedFormat('j M Y'))
                    ->wrap()
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('faculty.code')
                    ->label(__('admin.teaching_decree.fields.faculty'))
                    ->description(fn (TeachingDecree $record): ?string => $record->faculty?->name)
                    ->wrap()
                    ->extraAttributes(['class' => 'max-w-56']),

                Tables\Columns\TextColumn::make('period_start')
                    ->label(__('admin.teaching_decree.fields.period'))
                    ->formatStateUsing(fn (TeachingDecree $record): string => $record->period_start->translatedFormat('j M Y').' – '.$record->period_end->translatedFormat('j M Y'))
                    ->sortable(),

                Tables\Columns\TextColumn::make('period_status')
                    ->label(__('admin.dashboard.status'))
                    ->state(fn (TeachingDecree $record): DecreePeriod => $record->period())
                    ->badge(),

                Tables\Columns\TextColumn::make('assignment_summary')
                    ->label(__('admin.teaching_decree.fields.assignments'))
                    ->state(fn (TeachingDecree $record): string => self::assignmentSummary($record)),

                Tables\Columns\TextColumn::make('document')
                    ->label(__('admin.teaching_decree.fields.document'))
                    ->state(fn (TeachingDecree $record): string => __('admin.teaching_decree.document_state.'.($record->document ? 'yes' : 'no')))
                    ->badge()
                    ->color(fn (TeachingDecree $record): string => $record->document ? 'success' : 'warning')
                    ->icon(fn (TeachingDecree $record): string => $record->document ? 'lucide-circle-check' : 'lucide-circle-minus'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('faculty_id')
                    ->label(__('admin.teaching_decree.fields.faculty'))
                    ->options(fn (): array => self::facultyOptions())
                    ->searchable()
                    ->visible(fn (): bool => Filament::auth()->user()?->hasRole(UserRole::SuperAdmin) ?? false),

                Tables\Filters\SelectFilter::make('period')
                    ->label(__('admin.teaching_decree.fields.period'))
                    ->options(DecreePeriod::class)
                    ->query(fn (Builder $query, array $data): Builder => ($period = DecreePeriod::tryFrom((string) ($data['value'] ?? ''))) ? $query->inPeriod($period) : $query),

                Tables\Filters\SelectFilter::make('lecturer')
                    ->label(__('admin.lecturer.label'))
                    ->options(fn (): array => Lecturer::query()->visibleTo(Filament::auth()->user())->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->whereHas('assignments', fn (Builder $row) => $row->where('lecturer_id', $data['value']))
                        : $query),

                Tables\Filters\SelectFilter::make('course')
                    ->label(__('admin.course.label'))
                    ->options(fn (): array => Course::query()
                        ->whereHas('program', fn (Builder $program) => $program->visibleTo(Filament::auth()->user()))
                        ->packageOrder()
                        ->get()
                        ->mapWithKeys(fn (Course $course): array => [$course->getKey() => "{$course->code} · {$course->localizedName()}"])
                        ->all())
                    ->searchable()
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->whereHas('assignments', fn (Builder $row) => $row->where('course_id', $data['value']))
                        : $query),

                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\Action::make('download')
                    ->iconButton()
                    ->icon('lucide-download')
                    ->color('gray')
                    ->tooltip(__('admin.teaching_decree.download'))
                    ->visible(fn (TeachingDecree $record): bool => filled($record->document))
                    ->url($download),

                Tables\Actions\EditAction::make()
                    ->iconButton()
                    ->icon('lucide-pencil')
                    ->color('gray')
                    ->tooltip(__('admin.master.edit')),

                static::deleteAction()->after(fn (TeachingDecree $record) => $record->logActivity('deleted')),

                static::restoreAction()->after(fn (TeachingDecree $record) => $record->logActivity('restored')),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTeachingDecrees::route('/'),
            'create' => Pages\CreateTeachingDecree::route('/create'),
            'edit' => Pages\EditTeachingDecree::route('/{record}/edit'),
        ];
    }

    public static function assignmentSummary(TeachingDecree $decree): string
    {
        $lecturers = $decree->assignments->pluck('lecturer_id')->unique()->count();
        $courses = $decree->assignments->pluck('course_id')->unique()->count();

        return trans_choice('admin.teaching_decree.summary_lecturers', $lecturers, ['count' => $lecturers])
            .' · '.trans_choice('admin.teaching_decree.summary_courses', $courses, ['count' => $courses]);
    }

    /**
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

    /**
     * Dosen aktif dalam batas akses, ditambah dosen baris ini bila SK-nya sudah tersimpan: dosen
     * yang kini nonaktif, terhapus, atau dari fakultas lain tetap tampil di SK lama. "Baris ini" dari
     * data tersimpan, bukan isian browser, agar ID lain tidak bisa diselundupkan.
     *
     * @return array<string, string>
     */
    private static function lecturerOptions(?Model $row): array
    {
        $user = Filament::auth()->user();
        $current = $row instanceof TeachingAssignment ? $row->lecturer_id : null;

        return Lecturer::withTrashed()
            ->where(fn (Builder $query) => $query
                ->where(fn (Builder $available) => $available->visibleTo($user)->where('is_active', true)->whereNull('deleted_at'))
                ->when($current, fn (Builder $query) => $query->orWhere('id', $current)))
            ->with('faculty:id,code')
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (Lecturer $lecturer): array => [
                $lecturer->getKey() => $user?->hasRole(UserRole::SuperAdmin) ? "{$lecturer->name} ({$lecturer->faculty?->code})" : $lecturer->name,
            ])
            ->all();
    }

    /**
     * MK aktif dari program fakultas SK, ditambah MK baris ini selama fakultas SK belum diganti.
     *
     * @return array<string, string>
     */
    private static function courseOptions(?string $facultyId, ?Model $row): array
    {
        $current = $row instanceof TeachingAssignment && $row->decree?->faculty_id === $facultyId ? $row->course_id : null;

        return Course::withTrashed()
            ->where(fn (Builder $query) => $query
                ->where(fn (Builder $available) => $available
                    ->where('is_active', true)
                    ->whereNull('deleted_at')
                    ->whereHas('program', fn (Builder $program) => $program->where('faculty_id', $facultyId)))
                ->when($current, fn (Builder $query) => $query->orWhere('id', $current)))
            ->with('program:id,name')
            ->packageOrder()
            ->get()
            ->mapWithKeys(fn (Course $course): array => [
                $course->getKey() => "{$course->code} · {$course->localizedName()} ({$course->program?->name})",
            ])
            ->all();
    }
}
