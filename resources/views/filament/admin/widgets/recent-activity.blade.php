<x-filament-widgets::widget>
    <div class="sipma-card sipma-fill">
        <div class="sipma-card-head sipma-card-head-ruled">
            <h2 class="sipma-card-title">{{ __('admin.dashboard.activity') }}</h2>
        </div>

        @if ($items->isEmpty())
            <x-sipma.empty icon="lucide-activity" :title="__('admin.dashboard.activity_empty')" />
        @else
            <ul class="sipma-timeline">
                @foreach ($items as $item)
                    <li class="sipma-timeline-item">
                        <span class="sipma-timeline-dot sipma-tone-{{ $item['tone'] }}"></span>
                        <div>
                            <div class="sipma-timeline-text">{{ $item['text'] }}</div>
                            <div class="sipma-timeline-time">{{ $item['time'] }}</div>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</x-filament-widgets::widget>
