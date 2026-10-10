{{-- Dasbor kerja agen (setelah MOU disetujui): kartu angka, perlu tindakan, program MOU --}}
<div class="sipma-stat-grid is-five">
    @foreach ($cards as $card)
        <a href="{{ $card['url'] }}" class="sipma-card sipma-stat sipma-card-link">
            <div class="sipma-stat-head">
                <span class="sipma-stat-label">{{ $card['label'] }}</span>
                <span class="sipma-tile sipma-tone-{{ $card['tone'] }}">
                    <x-filament::icon :icon="$card['icon']" class="sipma-tile-icon" />
                </span>
            </div>

            <div class="sipma-stat-value">{{ \Illuminate\Support\Number::format($card['value'], locale: app()->getLocale()) }}</div>

            <div class="sipma-stat-foot">
                <span class="sipma-pill sipma-tone-{{ $card['badgeTone'] }}">{{ $card['badge'] }}</span>
            </div>
        </a>
    @endforeach
</div>

<div class="sipma-agent-grid is-wide">
    {{-- Perlu tindakan --}}
    <section class="sipma-card sipma-clip">
        <header class="sipma-card-head sipma-card-head-ruled">
            <div>
                <h2 class="sipma-card-title">
                    {{ __('agent.home.actions.title') }}
                    @if ($actionsTotal > 0)
                        <span class="sipma-card-count">{{ $actionsTotal }}</span>
                    @endif
                </h2>
                <p class="sipma-card-desc">{{ __('agent.home.actions.description') }}</p>
            </div>
            <a href="{{ \App\Filament\Agent\Resources\StudentResource::getUrl() }}" class="sipma-link">{{ __('admin.dashboard.see_all') }}</a>
        </header>

        @if ($actions->isEmpty())
            <x-sipma.empty icon="lucide-circle-check" :title="__('agent.home.actions.empty')" :description="__('agent.home.actions.empty_body')" />
        @else
            <ul class="sipma-todo">
                @foreach ($actions as $item)
                    <li class="sipma-todo-row">
                        <span class="sipma-avatar sipma-tone-{{ $item['avatarTone'] }}" aria-hidden="true">{{ $item['initials'] }}</span>
                        <div class="sipma-row-main">
                            <a href="{{ $item['url'] }}" class="sipma-person-name sipma-row-link">{{ $item['name'] }}</a>
                            <div class="sipma-person-meta">{{ $item['program'] }}</div>
                            <div @class(['sipma-todo-reason', 'sipma-tone-'.$item['tone']])>
                                <x-filament::icon :icon="$item['icon']" class="sipma-todo-icon" />
                                <span>{{ \Illuminate\Support\Str::limit($item['reason'], 110) }}</span>
                            </div>
                        </div>
                        <x-filament::button tag="a" :href="$item['url']" color="gray" size="sm">{{ $item['cta'] }}</x-filament::button>
                    </li>
                @endforeach
            </ul>
            @if ($actionsTotal > $actions->count())
                <p class="sipma-todo-more">{{ __('agent.home.actions.more', ['count' => $actionsTotal - $actions->count()]) }}</p>
            @endif
        @endif
    </section>

    {{-- Status MOU & program --}}
    <section class="sipma-card sipma-clip">
        <header class="sipma-card-head sipma-card-head-ruled">
            <div>
                <h2 class="sipma-card-title">{{ __('agent.home.mou.title') }}</h2>
                <p class="sipma-card-desc">{{ __('agent.home.mou.description', ['date' => $mou?->verified_at?->translatedFormat('j F Y') ?? '—']) }}</p>
            </div>
            <x-sipma.status-badge :status="$mou->status" />
        </header>
        <dl class="sipma-agent-facts">
            <div>
                <dt>{{ __('agent.mou.number') }}</dt>
                <dd class="sipma-mono">{{ $mou->mou_number ?? '—' }}</dd>
            </div>
        </dl>
        <div class="sipma-mou-programs">
            <h3 class="sipma-check-heading">{{ __('agent.home.mou.programs', ['count' => $programs->count()]) }}</h3>
            <ul>
                @foreach ($programs as $program)
                    <li>
                        <span class="sipma-mou-program-name">{{ $program['name'] }}</span>
                        @if ($program['period'])
                            <span class="sipma-pill sipma-tone-success">{{ __('agent.home.mou.open_until', ['date' => $program['period']->registration_closes_at->translatedFormat('j M')]) }}</span>
                        @else
                            <span class="sipma-pill sipma-tone-gray">{{ __('agent.home.mou.closed') }}</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
        <footer class="sipma-mou-foot">
            <a href="{{ \App\Filament\Agent\Pages\ManageMou::getUrl() }}" class="sipma-link">{{ __('agent.dashboard.stages.approved.cta') }}</a>
        </footer>
    </section>
</div>
