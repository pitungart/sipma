{{--
    Papan verifikasi (template). Seret kartu antar kolom = transisi StudentWorkflow yang sah;
    server menolak yang lain. Tombol langkah berikutnya di kartu adalah jalur tanpa seret (keyboard).
--}}
<div
    class="sipma-card sipma-board"
    x-data="{ dragging: null, over: null }"
>
    <div class="sipma-card-head sipma-card-head-ruled">
        <p id="sipma-board-hint" class="sipma-board-hint">
            <x-filament::icon :icon="$canMove ? 'lucide-grip-vertical' : 'lucide-eye'" class="sipma-board-hint-icon" />
            {{ $canMove ? __('admin.applicant.kanban.hint') : __('admin.applicant.kanban.readonly_hint') }}
        </p>
    </div>

    <div class="sipma-board-columns" aria-describedby="sipma-board-hint">
        @foreach ($columns as $column)
            @php($status = $column['status'])
            <section
                class="sipma-board-column sipma-tone-{{ $status->getColor() }}"
                aria-label="{{ $status->getLabel() }}"
                @if ($canMove)
                    x-on:dragover.prevent="over = @js($status->value)"
                    x-on:dragleave.self="over = null"
                    x-on:drop.prevent="
                        if (dragging && dragging.from !== @js($status->value)) {
                            $wire.moveCard(dragging.id, @js($status->value))
                        }
                        over = null
                        dragging = null
                    "
                    x-bind:class="{ 'is-over': over === @js($status->value) && dragging?.from !== @js($status->value) }"
                @endif
            >
                <header class="sipma-board-column-head">
                    <span class="sipma-status-dot"></span>
                    <span class="sipma-board-column-title" title="{{ $status->getLabel() }}">{{ __('admin.applicant.kanban.columns.'.$status->value) }}</span>
                    <span class="sipma-board-count">{{ $column['total'] }}</span>
                </header>

                <div class="sipma-board-cards">
                    @forelse ($column['cards'] as $card)
                        <article
                            class="sipma-board-card"
                            wire:key="card-{{ $card['id'] }}"
                            @if ($canMove)
                                draggable="true"
                                x-on:dragstart="dragging = { id: @js($card['id']), from: @js($status->value) }; $event.dataTransfer.effectAllowed = 'move'"
                                x-on:dragend="dragging = null; over = null"
                                x-bind:class="{ 'is-dragging': dragging?.id === @js($card['id']) }"
                            @endif
                        >
                            <a href="{{ $card['url'] }}" class="sipma-board-card-person">
                                <span class="sipma-avatar sipma-tone-{{ $card['tone'] }}" aria-hidden="true">{{ $card['initials'] }}</span>
                                <span class="sipma-min-0">
                                    <span class="sipma-board-card-name">{{ $card['name'] }}</span>
                                    <span class="sipma-board-card-meta">{{ $card['country'] ?? '—' }}</span>
                                </span>
                            </a>

                            <div class="sipma-board-chips">
                                <span class="sipma-board-chip">{{ $card['program'] }}</span>
                                <span class="sipma-board-chip is-muted">{{ $card['agent'] }}</span>
                            </div>

                            <div class="sipma-board-docs" title="{{ __('admin.applicant.documents_hint') }}">
                                <div class="sipma-progress"><div class="sipma-progress-fill sipma-bg-{{ $card['documentsTone'] }}" style="width: {{ $card['documentsPercent'] }}%"></div></div>
                                <span>{{ __('admin.applicant.kanban.documents', ['count' => $card['documents']]) }}</span>
                            </div>

                            @if ($card['next'] && $canMove)
                                @if ($card['next']['move'])
                                    <button type="button" class="sipma-board-next" wire:click="moveCard(@js($card['id']), @js($card['next']['move']))" wire:loading.attr="disabled">
                                        {{ $card['next']['label'] }} →
                                    </button>
                                @else
                                    <a href="{{ $card['url'] }}" class="sipma-board-next">{{ $card['next']['label'] }} →</a>
                                @endif
                            @endif
                        </article>
                    @empty
                        <p class="sipma-board-empty">
                            {{ __('admin.applicant.kanban.empty') }}
                            @if ($canMove)<br>{{ __('admin.applicant.kanban.drop_here') }}@endif
                        </p>
                    @endforelse

                    @if ($column['total'] > $column['cards']->count())
                        <a href="{{ \App\Filament\Admin\Resources\StudentResource::getUrl('index', ['activeTab' => $status->value]) }}" class="sipma-board-more">
                            {{ __('admin.applicant.kanban.more', ['count' => $column['total'] - $column['cards']->count()]) }}
                        </a>
                    @endif
                </div>
            </section>
        @endforeach
    </div>
</div>
