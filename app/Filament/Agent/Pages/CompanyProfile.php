<?php

namespace App\Filament\Agent\Pages;

use App\Filament\Agent\Concerns\InteractsWithAgent;
use App\Models\Agent;
use App\Models\Country;
use App\Workflow\AgentOnboarding;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Actions\Action as NotificationAction;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Gate;

/**
 * UC-12 Kelola profil agen (BPMN A3). Nama perusahaan & negara dikunci setelah MOU disetujui
 * (keputusan 7 Oktober 2026); kontak, PIC, dan alamat tetap bisa diubah.
 *
 * @property Form $form
 */
class CompanyProfile extends Page implements HasForms
{
    use InteractsWithAgent;
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'lucide-building-2';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'company';

    protected static string $view = 'filament.agent.pages.company-profile';

    /** @var array<string, mixed> */
    public array $data = [];

    public static function getNavigationLabel(): string
    {
        return __('agent.company.title');
    }

    public static function getNavigationBadge(): ?string
    {
        $agent = Filament::auth()->user()?->agent;

        return $agent?->isProfileComplete() ? null : '!';
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public function getTitle(): string
    {
        return __('agent.company.title');
    }

    public function getSubheading(): ?string
    {
        return __('agent.company.subheading');
    }

    public function mount(): void
    {
        $agent = $this->agent();

        $this->form->fill($agent?->only([...Agent::REQUIRED_PROFILE_FIELDS, 'last_name', 'email']) ?? []);
    }

    public function form(Form $form): Form
    {
        $locked = fn (): bool => $this->agent()?->isIdentityLocked() ?? false;
        $loginEmail = Filament::auth()->user()->email;

        return $form
            ->statePath('data')
            ->schema([
                Section::make(__('agent.company.sections.company'))
                    ->description(__('agent.company.sections.company_desc'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('company_name')
                            ->label(__('agent.company.fields.company_name'))
                            ->required()
                            ->maxLength(255)
                            ->disabled($locked)
                            ->helperText(fn (): ?string => $locked() ? __('agent.company.locked_hint') : null),
                        Select::make('country_code')
                            ->label(__('agent.company.fields.country_code'))
                            ->options(fn (): array => Country::options())
                            ->searchable()
                            ->required()
                            ->disabled($locked)
                            ->helperText(fn (): ?string => $locked() ? __('agent.company.locked_hint') : null),
                        Textarea::make('address')
                            ->label(__('agent.company.fields.address'))
                            ->required()
                            ->rows(3)
                            ->maxLength(1000)
                            ->columnSpanFull(),
                    ]),

                Section::make(__('agent.company.sections.contact'))
                    ->description(__('agent.company.sections.contact_desc'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('first_name')
                            ->label(__('agent.company.fields.first_name'))
                            ->required()
                            ->maxLength(255),
                        TextInput::make('last_name')
                            ->label(__('agent.company.fields.last_name'))
                            ->maxLength(255),
                        TextInput::make('email')
                            ->label(__('agent.company.fields.email'))
                            ->email()
                            ->maxLength(255)
                            ->placeholder($loginEmail)
                            ->helperText(__('agent.company.email_hint', ['email' => $loginEmail])),
                        TextInput::make('phone')
                            ->label(__('agent.company.fields.phone'))
                            ->tel()
                            ->required()
                            ->maxLength(50)
                            ->helperText(__('agent.company.phone_hint')),
                    ]),
            ]);
    }

    /**
     * @return list<Action>
     */
    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label(__('agent.company.save'))
                ->submit('save')
                ->keyBindings(['mod+s']),
        ];
    }

    public function save(): void
    {
        $user = Filament::auth()->user();
        $agent = $this->agent();

        $agent === null
            ? Gate::authorize('create', Agent::class)
            : Gate::authorize('update', $agent);

        $data = $this->form->getState();
        $data['email'] = filled($data['email'] ?? null) ? $data['email'] : $user->email;

        // Kolom yang dikunci tidak pernah ikut tersimpan, walau dikirim lewat Livewire
        if ($agent?->isIdentityLocked()) {
            $data = array_diff_key($data, array_flip(Agent::IDENTITY_FIELDS));
        }

        $agent = $user->agent()->updateOrCreate([], $data);
        $this->form->fill($agent->only([...Agent::REQUIRED_PROFILE_FIELDS, 'last_name', 'email']));

        $notification = Notification::make()->success()->title(__('agent.company.saved'));

        if (AgentOnboarding::for($agent)->stage() === AgentOnboarding::MOU) {
            $notification
                ->body(__('agent.company.saved_next'))
                ->actions([
                    NotificationAction::make('mou')
                        ->label(__('agent.dashboard.stages.mou.cta'))
                        ->url(ManageMou::getUrl())
                        ->button(),
                ]);
        }

        $notification->send();
    }
}
