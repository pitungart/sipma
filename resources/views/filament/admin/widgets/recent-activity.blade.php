<x-filament-widgets::widget>
    <div class="sipma-card sipma-fill">
        <div class="sipma-card-head sipma-card-head-ruled">
            <h2 class="sipma-card-title">{{ __('admin.dashboard.activity') }}</h2>
        </div>

        @if ($items->isEmpty())
            <x-sipma.empty icon="lucide-activity" :title="__('admin.dashboard.activity_empty')" />
        @else
            <x-sipma.activity-timeline :items="$items" />
        @endif
    </div>
</x-filament-widgets::widget>
