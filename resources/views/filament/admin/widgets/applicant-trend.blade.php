<x-filament-widgets::widget>
    <div class="sipma-card sipma-chart-card sipma-fill" x-data="{ tip: null }">
        <div class="sipma-card-head">
            <div>
                <h2 class="sipma-card-title">{{ __('admin.dashboard.trend') }}</h2>
                <p class="sipma-card-desc">{{ __('admin.dashboard.trend_hint', ['current' => $year, 'previous' => $year - 1]) }}</p>
            </div>

            <div class="sipma-segment" role="tablist">
                @foreach ($sources as $key => $label)
                    <button
                        type="button"
                        role="tab"
                        wire:click="setSource('{{ $key }}')"
                        aria-selected="{{ $source === $key ? 'true' : 'false' }}"
                        @class(['sipma-segment-btn', 'is-active' => $source === $key])
                    >{{ $label }}</button>
                @endforeach
            </div>
        </div>

        <div class="sipma-legend">
            <span><span class="sipma-legend-line"></span>{{ $year }}</span>
            <span><span class="sipma-legend-dash"></span>{{ $year - 1 }}</span>
        </div>

        {{--
            Area plot setinggi tetap: garis & area digambar SVG yang direntang selebar kartu
            (preserveAspectRatio="none" + non-scaling-stroke), sedangkan label sumbu, titik, dan
            zona hover adalah HTML — ukuran teksnya tetap 11px berapa pun lebar layarnya.
        --}}
        <div class="sipma-chart" x-on:mouseleave="tip = null">
            <div class="sipma-chart-yaxis" aria-hidden="true">
                @foreach ($ticks as $tick)
                    <span style="top: {{ $tick['top'] }}%">{{ $tick['value'] }}</span>
                @endforeach
            </div>

            <div class="sipma-chart-plot">
                <svg viewBox="0 0 {{ $width }} {{ $height }}" preserveAspectRatio="none" class="sipma-chart-svg" role="img" aria-label="{{ __('admin.dashboard.trend') }}">
                    <defs>
                        <linearGradient id="sipma-trend-fill" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0" class="sipma-chart-stop-top" />
                            <stop offset="1" class="sipma-chart-stop-bottom" />
                        </linearGradient>
                    </defs>

                    @foreach ($ticks as $tick)
                        <line x1="0" x2="{{ $width }}" y1="{{ $tick['y'] }}" y2="{{ $tick['y'] }}" class="sipma-chart-grid" />
                    @endforeach

                    @if ($area)
                        <path d="{{ $area }}" fill="url(#sipma-trend-fill)" />
                    @endif

                    <polyline points="{{ $previousPoints }}" class="sipma-chart-prev" />

                    @if ($currentPoints)
                        <polyline points="{{ $currentPoints }}" class="sipma-chart-line" />
                    @endif
                </svg>

                @foreach ($dots as $dot)
                    <span class="sipma-chart-dot" style="left: {{ $dot['x'] }}%; top: {{ $dot['y'] }}%"></span>
                @endforeach

                {{-- Zona hover per bulan, selebar satu kolom --}}
                @foreach ($months as $i => $month)
                    <span
                        class="sipma-chart-hover"
                        style="left: {{ $month['x'] - 100 / 24 }}%; width: {{ 100 / 12 }}%"
                        x-on:mouseenter="tip = @js(['label' => $month['long'], 'current' => $month['current'], 'previous' => $month['previous'], 'left' => $month['x'], 'top' => $month['y']])"
                    ></span>
                @endforeach

                <template x-if="tip">
                    <span class="sipma-chart-guide" x-bind:style="`left: ${tip.left}%`"></span>
                </template>

                {{-- Tooltip di atas titiknya; dibalik ke bawah bila titik terlalu dekat tepi atas --}}
                <template x-if="tip">
                    <div
                        class="sipma-chart-tip"
                        x-bind:class="{ 'is-below': tip.top < 35 }"
                        x-bind:style="`left: ${tip.left}%; top: ${tip.top}%`"
                    >
                        <div class="sipma-chart-tip-title" x-text="tip.label"></div>
                        <div>
                            {{ $year }}: <b x-text="tip.current ?? '–'"></b>
                            · {{ $year - 1 }}: <span x-text="tip.previous"></span>
                        </div>
                    </div>
                </template>
            </div>

            <div class="sipma-chart-xaxis" aria-hidden="true">
                @foreach ($months as $month)
                    <span style="left: {{ $month['x'] }}%">{{ $month['label'] }}</span>
                @endforeach
            </div>
        </div>
    </div>
</x-filament-widgets::widget>
