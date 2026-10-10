{{-- UC-13 MOU agen: status terbaru, ketentuan berkas (tampil sebelum unggah, R-3.8), riwayat --}}
@php
    use App\Enums\MouStatus;
    use App\Workflow\AgentOnboarding;

    $key = $latest?->status->value ?? 'none';
    $tone = $latest?->status->getColor() ?? 'warning';
    $date = match ($key) {
        'pending' => $latest->created_at,
        'none' => null,
        default => $latest->verified_at ?? $latest->updated_at,
    };
@endphp

<x-filament-panels::page class="sipma-agent-mou">
    @if ($stage === AgentOnboarding::PROFILE)
        <div class="sipma-alert sipma-tone-warning" role="status">
            <x-filament::icon icon="lucide-triangle-alert" class="sipma-alert-icon" />
            <div>{{ __('agent.mou.profile_needed') }}</div>
        </div>
    @endif

    {{-- Status MOU terbaru --}}
    <section class="sipma-card sipma-clip">
        <div @class(['sipma-agent-status', 'sipma-tone-'.$tone])>
            <span class="sipma-agent-next-icon">
                <x-filament::icon :icon="$latest?->status->getIcon() ?? 'lucide-file-up'" class="sipma-agent-next-svg" />
            </span>
            <div class="sipma-min-0">
                <h2 class="sipma-agent-next-title">{{ __("agent.mou.status.{$key}.title") }}</h2>
                <p class="sipma-agent-next-body">{{ __("agent.mou.status.{$key}.body", ['date' => $date?->translatedFormat('j F Y')]) }}</p>
            </div>
            @if ($latest)
                <x-sipma.status-badge :status="$latest->status" />
            @endif
        </div>

        @if ($latest?->status === MouStatus::Rejected && filled($latest->revision_note))
            <div class="sipma-agent-status-note">
                <div class="sipma-alert sipma-tone-danger" role="status">
                    <x-filament::icon icon="lucide-triangle-alert" class="sipma-alert-icon" />
                    <div><b>{{ __('agent.mou.note') }}</b> {{ $latest->revision_note }}</div>
                </div>
            </div>
        @endif

        @if ($latest)
            <dl class="sipma-agent-facts is-ruled">
                <div>
                    <dt>{{ __('agent.mou.number') }}</dt>
                    <dd @class(['sipma-mono', 'is-empty' => blank($latest->mou_number)])>{{ $latest->mou_number ?? '—' }}</dd>
                </div>
                <div>
                    <dt>{{ __('agent.mou.uploaded_at') }}</dt>
                    <dd>{{ $latest->created_at->translatedFormat('j M Y, H:i') }}</dd>
                </div>
                <div>
                    <dt>{{ __('agent.mou.reviewed_at') }}</dt>
                    <dd @class(['is-empty' => ! $latest->verified_at])>{{ $latest->verified_at?->translatedFormat('j M Y, H:i') ?? '—' }}</dd>
                </div>
                @if ($latest->status === MouStatus::Approved)
                    <div class="sipma-agent-facts-wide">
                        <dt>{{ __('agent.mou.programs') }}</dt>
                        <dd class="sipma-chips">
                            @forelse ($latest->programs->sortBy('name') as $program)
                                <span class="sipma-row-tag">{{ $program->name }}</span>
                            @empty
                                —
                            @endforelse
                        </dd>
                    </div>
                @endif
            </dl>
        @endif
    </section>

    {{-- Ketentuan berkas: tampil sebelum tombol unggah dipakai --}}
    @unless ($latest?->status === MouStatus::Approved)
        <section class="sipma-card sipma-clip">
            <header class="sipma-card-head sipma-card-head-ruled">
                <div>
                    <h2 class="sipma-card-title">{{ __('agent.mou.rules.title') }}</h2>
                    <p class="sipma-card-desc">{{ __('agent.mou.template_desc') }}</p>
                </div>
            </header>
            <ol class="sipma-agent-rules">
                @foreach (__('agent.mou.rules.items') as $rule)
                    <li>
                        <span class="sipma-agent-rule-num">{{ $loop->iteration }}</span>
                        <span>{{ $rule }}</span>
                    </li>
                @endforeach
            </ol>
        </section>
    @endunless

    {{-- Riwayat --}}
    <section class="sipma-card sipma-clip">
        <header class="sipma-card-head sipma-card-head-ruled">
            <div>
                <h2 class="sipma-card-title">{{ __('agent.mou.history') }}</h2>
                <p class="sipma-card-desc">{{ __('agent.mou.history_desc') }}</p>
            </div>
        </header>

        @if ($history->isEmpty())
            <x-sipma.empty icon="lucide-file-text" :title="__('agent.mou.empty')" :description="__('agent.mou.status.none.body')" />
        @else
            <ul class="sipma-rows sipma-section">
                @foreach ($history as $mou)
                    <li class="sipma-row" wire:key="mou-{{ $mou->getKey() }}">
                        <span @class(['sipma-tile', 'sipma-tone-'.$mou->status->getColor()])>
                            <x-filament::icon icon="lucide-file-text" class="sipma-tile-icon" />
                        </span>
                        <div class="sipma-row-main">
                            <div class="sipma-row-title">
                                {{ __('agent.mou.history_item', ['date' => $mou->created_at->translatedFormat('j M Y')]) }}
                                @if ($mou->mou_number)
                                    <span class="sipma-row-tag sipma-mono">{{ $mou->mou_number }}</span>
                                @endif
                            </div>
                            <div class="sipma-row-meta">
                                {{ $mou->created_at->translatedFormat('H:i') }}
                                @if ($mou->verified_at)
                                    · {{ __('agent.mou.reviewed_at') }} {{ $mou->verified_at->translatedFormat('j M Y') }}
                                @endif
                            </div>
                            @if ($mou->status === MouStatus::Rejected && filled($mou->revision_note))
                                <div class="sipma-row-note">{{ $mou->revision_note }}</div>
                            @endif
                        </div>
                        <x-sipma.status-badge :status="$mou->status" />
                        <div class="sipma-row-actions">
                            {{ ($this->previewMouAction)(['mou' => $mou->getKey()]) }}
                            <x-filament::button
                                tag="a"
                                :href="route('files.mou', $mou).'?download=1'"
                                color="gray"
                                size="sm"
                                icon="lucide-download"
                            >
                                {{ __('agent.mou.actions.download') }}
                            </x-filament::button>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</x-filament-panels::page>
