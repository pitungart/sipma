<x-filament-widgets::widget>
    <div class="sipma-card sipma-fill">
        <div class="sipma-card-head">
            <div>
                <h2 class="sipma-card-title">{{ __('admin.dashboard.by_program') }}</h2>
                <p class="sipma-card-desc">{{ __('admin.dashboard.by_program_hint', ['year' => $year]) }}</p>
            </div>
        </div>

        @if ($bars->isEmpty())
            <x-sipma.empty icon="lucide-graduation-cap" :title="__('admin.dashboard.by_program_empty')" />
        @else
            <ul class="sipma-bars">
                @foreach ($bars as $bar)
                    <li class="sipma-bar">
                        <div class="sipma-bar-row">
                            <span class="sipma-bar-name">{{ $bar['name'] }}</span>
                            <span class="sipma-bar-value">{{ $bar['value'] }}</span>
                        </div>
                        <div class="sipma-progress"><div class="sipma-progress-fill" style="width: {{ $bar['width'] }}%"></div></div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</x-filament-widgets::widget>
