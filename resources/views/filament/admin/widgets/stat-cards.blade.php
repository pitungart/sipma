<x-filament-widgets::widget>
    <div class="sipma-stat-grid">
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
                    <span class="sipma-stat-caption">{{ $card['caption'] }}</span>
                </div>
            </a>
        @endforeach
    </div>
</x-filament-widgets::widget>
