<?php

namespace App\Filament\Admin\Resources;

use App\Enums\StudentCourseStatus;
use App\Enums\UserRole;
use App\Filament\Admin\Resources\Concerns\AcademicModule;
use App\Filament\Admin\Resources\Concerns\MasterData;
use App\Filament\Admin\Resources\CourseResource\Pages;
use App\Models\Course;
use App\Models\Faculty;
use App\Models\Program;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Pages\ManageRecords;
use Filament\Resources\Resource;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule as ValidationRule;
use Illuminate\Validation\Rules\In;
use Illuminate\Validation\Rules\Unique;

/**
 * TP-3 Paket mata kuliah per program. Pola halamannya sama dengan master data (daftar + modal),
 * tetapi berada di grup Akademik dan tidak ikut tab Data master.
 */
class CourseResource extends Resource
{
    use AcademicModule, MasterData {
        AcademicModule::getNavigationGroup insteadof MasterData;
        AcademicModule::getNavigationLabel insteadof MasterData;
    }

    protected static ?string $model = Course::class;

    protected static ?string $slug = 'courses';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationIcon = 'lucide-book-open';

    protected static ?int $navigationSort = 3;

    public static function getModelLabel(): string
    {
        return __('admin.course.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.course.plural');
    }

    public static function masterDescription(): string
    {
        return __('admin.course.description');
    }

    public static function showsInMasterTabs(): bool
    {
        return false;
    }

    public static function modalSubtitle(): string
    {
        return __('admin.groups.academic').' · '.static::getPluralModelLabel();
    }

    public static function modalWidth(): MaxWidth
    {
        return MaxWidth::TwoExtraLarge;
    }

    /**
     * Admin Fakultas tidak pernah melihat MK fakultas lain, termasuk lewat URL langsung.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]) // filter "dihapus" mengaturnya sendiri
            ->whereHas('program', fn (Builder $program) => $program->visibleTo(Filament::auth()->user()))
            ->with('program.faculty')
            ->withCount(['studentCourses as students_taking' => fn (Builder $query): Builder => $query->where('status', '!=', StudentCourseStatus::Dropped)]);
    }

    /**
     * URL paket satu program, dipakai kolom "n MK" di Master Program.
     */
    public static function urlForProgram(Program $program): string
    {
        return static::getUrl('index', ['tableFilters' => ['program_id' => ['value' => $program->getKey()]]]);
    }

    public static function form(Form $form): Form
    {
        return $form->columns(2)->schema([
            // Bawaan: program yang sedang disaring, agar mengisi satu paket tidak perlu memilih berulang
            Forms\Components\Select::make('program_id')
                ->label(__('admin.program.label'))
                ->options(fn (): array => self::programOptions())
                ->default(fn ($livewire): ?string => self::filteredProgramId($livewire))
                ->rule(fn (): In => ValidationRule::in(array_keys(self::programOptions())))
                ->searchable()
                ->required()
                ->live()
                ->columnSpanFull(),

            Forms\Components\TextInput::make('code')
                ->label(__('admin.course.fields.code'))
                ->required()
                ->maxLength(20)
                ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule->where('program_id', $get('program_id')))
                ->validationMessages(['unique' => __('admin.course.code_taken')])
                ->dehydrateStateUsing(fn (string $state): string => mb_strtoupper(trim($state))),

            Forms\Components\TextInput::make('credits')
                ->label(__('admin.course.fields.credits'))
                ->helperText(__('admin.course.fields.credits_hint'))
                ->numeric()
                ->integer()
                ->minValue(1)
                ->maxValue(10),

            Forms\Components\TextInput::make('name')
                ->label(__('admin.course.fields.name'))
                ->required()
                ->maxLength(255),

            Forms\Components\TextInput::make('name_en')
                ->label(__('admin.course.fields.name_en'))
                ->helperText(__('admin.course.fields.name_en_hint'))
                ->maxLength(255),

            Forms\Components\TextInput::make('semester')
                ->label(__('admin.course.fields.semester'))
                ->helperText(__('admin.course.fields.semester_hint'))
                ->numeric()
                ->integer()
                ->minValue(1)
                ->maxValue(14),

            Forms\Components\Textarea::make('description')
                ->label(__('admin.course.fields.description'))
                ->rows(3)
                ->columnSpanFull(),

            static::activeField()->helperText(__('admin.course.fields.active_hint')),
        ]);
    }

    public static function table(Table $table): Table
    {
        return static::masterTable($table)
            ->defaultSort(fn (Builder $query): Builder => $query->orderBy('program_id')->packageOrder())
            ->searchPlaceholder(__('admin.common.search', ['label' => Str::lower(__('admin.course.search'))]))
            ->header(fn ($livewire) => self::summary($livewire))
            ->emptyStateIcon('lucide-book-open')
            ->emptyStateHeading(__('admin.course.empty_heading'))
            ->emptyStateDescription(__('admin.course.empty_description'))
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label(__('admin.course.fields.code'))
                    ->fontFamily('mono')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->label(__('admin.course.fields.name'))
                    ->description(fn (Course $record): ?string => $record->name_en)
                    ->wrap()
                    ->searchable(['name', 'name_en'])
                    ->sortable(),

                Tables\Columns\TextColumn::make('credits')
                    ->label(__('admin.course.fields.credits'))
                    ->placeholder('—')
                    ->sortable(),

                Tables\Columns\TextColumn::make('semester')
                    ->label(__('admin.course.fields.semester'))
                    ->placeholder('—')
                    ->sortable(),

                Tables\Columns\TextColumn::make('program.name')
                    ->label(__('admin.program.label'))
                    ->description(fn (Course $record): ?string => $record->program?->faculty?->name)
                    ->wrap(),

                // Tanpa yang di-drop
                Tables\Columns\TextColumn::make('students_taking')
                    ->label(__('admin.course.fields.students'))
                    ->state(fn (Course $record): int => (int) $record->students_taking)
                    ->sortable(),

                static::activeColumn(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('faculty')
                    ->label(__('admin.academic_student.fields.faculty'))
                    ->visible(fn (): bool => Filament::auth()->user()?->hasRole(UserRole::SuperAdmin) === true)
                    ->options(fn (): array => Faculty::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->whereHas('program', fn (Builder $program) => $program->where('faculty_id', $data['value']))
                        : $query),

                Tables\Filters\SelectFilter::make('program_id')
                    ->label(__('admin.program.label'))
                    ->options(fn (): array => self::programOptions())
                    ->searchable(),

                // Hanya semester yang memang ada di paket
                Tables\Filters\SelectFilter::make('semester')
                    ->label(__('admin.course.fields.semester'))
                    ->options(fn (): array => static::getEloquentQuery()
                        ->withoutEagerLoads()
                        ->whereNotNull('semester')
                        ->distinct()
                        ->orderBy('semester')
                        ->pluck('semester', 'semester')
                        ->all()),

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
            'index' => Pages\ManageCourses::route('/'),
        ];
    }

    /**
     * Program yang boleh diisi paketnya; Admin Fakultas hanya fakultasnya. Program nonaktif tetap
     * bisa dipilih agar paketnya bisa disiapkan sebelum program dibuka.
     *
     * @return array<string, string>
     */
    private static function programOptions(): array
    {
        return Program::query()->visibleTo(Filament::auth()->user())->orderBy('name')->pluck('name', 'id')->all();
    }

    private static function filteredProgramId(mixed $livewire): ?string
    {
        return $livewire instanceof ManageRecords ? ($livewire->tableFilters['program_id']['value'] ?? null) : null;
    }

    /**
     * Ringkasan di atas tabel saat satu program disaring: jumlah MK aktif dan total SKS.
     */
    private static function summary(mixed $livewire): mixed
    {
        $program = Program::query()->visibleTo(Filament::auth()->user())->find(self::filteredProgramId($livewire));

        if ($program === null) {
            return null;
        }

        $courses = $program->courses()->active()->get(['id', 'credits']);

        return view('filament.admin.courses.summary', [
            'program' => $program,
            'count' => $courses->count(),
            'credits' => $courses->sum('credits'),
        ]);
    }
}
