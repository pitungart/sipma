<?php

namespace App\Filament\Agent\Pages;

use App\Filament\Agent\Concerns\InteractsWithAgent;
use App\Filament\Agent\Resources\StudentResource;
use App\Support\AgentDashboard;
use App\Workflow\AgentOnboarding;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Contracts\View\View;

/**
 * Dashboard /agent. Selama onboarding (BPMN A3–A6): langkah profil → MOU → persetujuan KUI,
 * kartu "langkah berikutnya", dan pendaftaran mahasiswa yang terkunci. Setelah MOU disetujui:
 * dasbor kerja — kartu angka per tahap, "perlu tindakan", dan program MOU (App\Support\AgentDashboard).
 */
class Dashboard extends BaseDashboard
{
    use InteractsWithAgent;

    protected static ?string $navigationIcon = 'lucide-house';

    protected static string $view = 'filament.agent.dashboard';

    public function getHeader(): ?View
    {
        return view('filament.admin.dashboard-header', [
            'date' => now()->translatedFormat('l, j F Y'),
            'greeting' => __('agent.dashboard.greeting', ['name' => Filament::getUserName(Filament::auth()->user())]),
            'subheading' => __($this->onboarding()->stage() === AgentOnboarding::APPROVED
                ? 'agent.dashboard.subheading_done'
                : 'agent.dashboard.subheading'),
            'actions' => $this->getCachedHeaderActions(),
        ]);
    }

    /**
     * @return list<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('addStudent')
                ->label(__('agent.students.actions.create'))
                ->icon('lucide-plus')
                ->visible(fn (): bool => StudentResource::canAccess() && StudentResource::canCreate())
                ->url(fn (): string => StudentResource::getUrl('create')),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $onboarding = $this->onboarding();
        $stage = $onboarding->stage();
        $agent = $onboarding->agent;

        // Setelah MOU disetujui: dasbor kerja (kartu angka, perlu tindakan, program MOU)
        if ($stage === AgentOnboarding::APPROVED) {
            $home = new AgentDashboard($agent);
            $actions = $home->actions();

            return [
                'workspace' => true,
                'cards' => $home->cards(),
                'actions' => $actions->take(AgentDashboard::ACTION_LIMIT),
                'actionsTotal' => $actions->count(),
                'mou' => $onboarding->latestMou,
                'programs' => $home->programs(),
            ];
        }

        return [
            'workspace' => false,
            'onboarding' => $onboarding,
            'stage' => $stage,
            'next' => [
                'title' => __("agent.dashboard.stages.{$stage}.title"),
                'body' => __("agent.dashboard.stages.{$stage}.body", ['number' => $onboarding->latestMou?->mou_number ?? '—']),
                'cta' => __("agent.dashboard.stages.{$stage}.cta"),
                'url' => $stage === AgentOnboarding::PROFILE ? CompanyProfile::getUrl() : ManageMou::getUrl(),
                'tone' => match ($stage) {
                    AgentOnboarding::REJECTED => 'danger',
                    AgentOnboarding::PENDING => 'info',
                    AgentOnboarding::APPROVED => 'success',
                    default => 'warning',
                },
                'icon' => match ($stage) {
                    AgentOnboarding::PROFILE => 'lucide-building-2',
                    AgentOnboarding::REJECTED => 'lucide-circle-x',
                    AgentOnboarding::PENDING => 'lucide-clock',
                    AgentOnboarding::APPROVED => 'lucide-circle-check',
                    default => 'lucide-file-up',
                },
            ],
            'profile' => [
                __('agent.company.fields.company_name') => $agent?->company_name,
                __('agent.company.fields.country_code') => $agent?->country?->name,
                __('agent.company.sections.contact') => trim(($agent?->first_name ?? '').' '.($agent?->last_name ?? '')) ?: null,
                __('agent.company.fields.phone') => $agent?->phone,
            ],
        ];
    }
}
