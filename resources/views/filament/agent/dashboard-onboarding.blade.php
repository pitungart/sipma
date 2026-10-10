{{-- Dasbor agen selama onboarding (BPMN A3–A6): langkah, langkah berikutnya, ringkasan profil,
     dan pendaftaran mahasiswa yang terkunci sampai MOU disetujui --}}
@php
    use App\Workflow\AgentOnboarding;

    $latest = $onboarding->latestMou;
    $unlocked = $stage === AgentOnboarding::APPROVED;
@endphp

<x-sipma.status-timeline :steps="$onboarding->steps()" :label="__('agent.onboarding.label')" />

<div class="sipma-agent-grid">
    {{-- Langkah berikutnya --}}
    <section @class(['sipma-card sipma-agent-next', 'sipma-tone-'.$next['tone']])>
        <span class="sipma-agent-next-icon">
            <x-filament::icon :icon="$next['icon']" class="sipma-agent-next-svg" />
        </span>
        <div class="sipma-min-0">
            <p class="sipma-agent-kicker">{{ __(in_array($stage, [AgentOnboarding::PENDING, AgentOnboarding::APPROVED], true) ? 'agent.dashboard.status' : 'agent.dashboard.next_step') }}</p>
            <h2 class="sipma-agent-next-title">{{ $next['title'] }}</h2>
            <p class="sipma-agent-next-body">{{ $next['body'] }}</p>

            @if ($stage === AgentOnboarding::REJECTED && filled($latest?->revision_note))
                <div class="sipma-alert sipma-tone-danger sipma-agent-note" role="status">
                    <x-filament::icon icon="lucide-triangle-alert" class="sipma-alert-icon" />
                    <div><b>{{ __('agent.mou.note') }}</b> {{ $latest->revision_note }}</div>
                </div>
            @endif

            <x-filament::button
                tag="a"
                :href="$next['url']"
                :color="in_array($stage, [AgentOnboarding::PENDING, AgentOnboarding::APPROVED], true) ? 'gray' : 'primary'"
                icon="lucide-arrow-right"
                icon-position="after"
                class="sipma-agent-next-cta"
            >
                {{ $next['cta'] }}
            </x-filament::button>
        </div>
    </section>

    {{-- Ringkasan profil --}}
    <section class="sipma-card sipma-clip">
        <header class="sipma-card-head sipma-card-head-ruled">
            <h2 class="sipma-card-title">{{ __('agent.dashboard.profile_card') }}</h2>
            <a href="{{ \App\Filament\Agent\Pages\CompanyProfile::getUrl() }}" class="sipma-link">{{ __('agent.dashboard.edit_profile') }}</a>
        </header>
        <dl class="sipma-agent-facts">
            @foreach ($profile as $label => $value)
                <div>
                    <dt>{{ $label }}</dt>
                    <dd @class(['is-empty' => blank($value)])>{{ $value ?: __('agent.dashboard.not_filled') }}</dd>
                </div>
            @endforeach
        </dl>
    </section>
</div>

{{-- Pendaftaran mahasiswa: terkunci sampai MOU disetujui (BPMN S3) --}}
<section @class(['sipma-card sipma-agent-gate', 'is-locked' => ! $unlocked])>
    <span @class(['sipma-tile', $unlocked ? 'sipma-tone-success' : 'sipma-tone-gray'])>
        <x-filament::icon :icon="$unlocked ? 'lucide-lock-open' : 'lucide-lock'" class="sipma-tile-icon" />
    </span>
    <div class="sipma-min-0">
        <h2 class="sipma-agent-gate-title">{{ __('agent.dashboard.students.title') }}</h2>
        <p class="sipma-agent-gate-body">{{ __($unlocked ? 'agent.dashboard.students.open_body' : 'agent.dashboard.students.locked_body') }}</p>
    </div>
    @if ($unlocked)
        <x-filament::button tag="a" :href="\App\Filament\Agent\Resources\StudentResource::getUrl()" icon="lucide-arrow-right" icon-position="after" size="sm">
            {{ __('agent.students.actions.open_list') }}
        </x-filament::button>
    @else
        <span class="sipma-status sipma-tone-gray">
            <span class="sipma-status-dot"></span>{{ __('agent.dashboard.students.locked') }}
        </span>
    @endif
</section>
