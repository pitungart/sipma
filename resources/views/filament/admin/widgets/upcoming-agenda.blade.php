<x-filament-widgets::widget>
    <div class="sipma-card sipma-fill">
        <div class="sipma-card-head sipma-card-head-ruled">
            <h2 class="sipma-card-title">{{ __('admin.dashboard.agenda') }}</h2>
            @if ($link)
                <a href="{{ $link }}" class="sipma-link">{{ __('admin.dashboard.agenda_link') }}</a>
            @endif
        </div>

        @if ($events->isEmpty())
            <x-sipma.empty icon="lucide-calendar-days" :title="__('admin.dashboard.agenda_empty')" />
        @else
            <ul class="sipma-agenda">
                @foreach ($events as $event)
                    <li class="sipma-agenda-item">
                        <span class="sipma-date-tile sipma-tone-{{ $event['tone'] }}">
                            <span class="sipma-date-tile-day">{{ $event['day'] }}</span>
                            <span class="sipma-date-tile-month">{{ $event['month'] }}</span>
                        </span>
                        <div class="sipma-min-0">
                            <div class="sipma-agenda-title">{{ $event['title'] }}</div>
                            <div class="sipma-agenda-meta">{{ $event['meta'] }}</div>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</x-filament-widgets::widget>
