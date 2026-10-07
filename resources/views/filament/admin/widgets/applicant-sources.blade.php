<x-filament-widgets::widget>
    <div class="sipma-card sipma-fill">
        <div class="sipma-card-head sipma-card-head-ruled">
            <h2 class="sipma-card-title">{{ __('admin.dashboard.sources') }}</h2>
        </div>

        <div class="sipma-donut-wrap">
            <div class="sipma-donut">
                <svg viewBox="0 0 120 120" class="sipma-donut-svg" aria-hidden="true">
                    <circle cx="60" cy="60" r="{{ $radius }}" class="sipma-donut-track" />
                    @if ($total > 0)
                        <circle cx="60" cy="60" r="{{ $radius }}" stroke-dasharray="{{ $agentDash }}" class="sipma-donut-seg sipma-stroke-primary" />
                        <circle cx="60" cy="60" r="{{ $radius }}" stroke-dasharray="{{ $selfDash }}" stroke-dashoffset="{{ $selfOffset }}" class="sipma-donut-seg sipma-stroke-warning" />
                    @endif
                </svg>
                <div class="sipma-donut-center">
                    <div class="sipma-donut-total">{{ \Illuminate\Support\Number::format($total, locale: app()->getLocale()) }}</div>
                    <div class="sipma-donut-caption">{{ __('admin.dashboard.sources_total') }}</div>
                </div>
            </div>

            <div class="sipma-donut-legend">
                @foreach ($rows as $row)
                    <div class="sipma-donut-row">
                        <span class="sipma-swatch sipma-bg-{{ $row['tone'] }}"></span>
                        <span class="sipma-donut-label">{{ $row['label'] }}</span>
                        <span class="sipma-donut-value">{{ $row['value'] }}</span>
                        <span class="sipma-donut-pct">{{ $row['percent'] }}</span>
                    </div>
                @endforeach
                <div class="sipma-donut-note">{{ $agencies }}</div>
            </div>
        </div>
    </div>
</x-filament-widgets::widget>
