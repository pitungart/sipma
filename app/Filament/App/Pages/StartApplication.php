<?php

namespace App\Filament\App\Pages;

use App\Enums\StudentStatus;
use App\Filament\App\Concerns\InteractsWithApplication;
use App\Filament\Support\ApplicantForm;
use App\Models\AcademicPeriod;
use App\Models\Student;
use Filament\Facades\Filament;
use Filament\Forms\Components\Wizard;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\Url;

/**
 * UC-07 + UC-04: mulai pendaftaran (satu akun = satu pendaftaran, keputusan 8 Oktober 2026).
 * Wizard empat langkah yang bisa dilompati; draf cukup nama, email, paspor, dan program.
 * Nama & email terisi dari akun; program hanya yang periodenya sedang dibuka, tanpa biaya.
 *
 * @property Form $form
 */
class StartApplication extends Page implements HasForms
{
    use InteractsWithApplication;
    use InteractsWithForms;

    protected static ?string $slug = 'application/start';

    protected static string $view = 'filament.app.start';

    protected static bool $shouldRegisterNavigation = false;

    /** Program dari katalog (?program=…) */
    #[Url]
    public ?string $program = null;

    /** @var array<string, mixed> */
    public array $data = [];

    /**
     * Hanya bila akun belum punya pendaftaran (StudentPolicy::create).
     */
    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user !== null && Gate::forUser($user)->allows('create', Student::class);
    }

    public function getTitle(): string
    {
        return __('student.application.start_title');
    }

    public function getSubheading(): ?string
    {
        return __('student.application.start_description');
    }

    /**
     * @return array<int|string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [MyApplication::getUrl() => __('student.application.title'), __('student.application.start_title')];
    }

    public function mount(): void
    {
        $user = Filament::auth()->user();

        $this->form->fill([
            'full_name' => $user->name,
            'email' => $user->email,
            'program_id' => array_key_exists((string) $this->program, static::programOptions()) ? $this->program : null,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->schema([
                Wizard::make(ApplicantForm::steps([
                    ApplicantForm::programSelect(fn (): array => static::programOptions())
                        ->helperText(__('student.application.program_hint'))
                        ->columnSpanFull(),
                ], __('student.application.program_step_hint')))
                    ->skippable()
                    ->submitAction(new HtmlString(Blade::render(
                        '<x-filament::button type="submit" wire:target="create">{{ $label }}</x-filament::button>',
                        ['label' => __('admin.applicant.actions.save_draft')],
                    ))),
            ]);
    }

    public function create(): void
    {
        abort_unless(static::canAccess(), 403);

        $data = $this->form->getState();

        $student = Student::create([
            ...$data,
            'user_id' => Filament::auth()->id(),
            'agent_id' => null,
            'academic_period_id' => AcademicPeriod::currentFor($data['program_id'] ?? null)?->getKey(),
            'status' => StudentStatus::Draft,
        ]);

        Notification::make()
            ->success()
            ->title(__('agent.students.done.created'))
            ->body(__('agent.students.done.created_body'))
            ->send();

        $this->redirect(MyApplication::getUrl(), navigate: false);
    }
}
