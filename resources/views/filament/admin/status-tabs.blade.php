{{-- Tab status di kepala kartu tabel (template): label + jumlah; mengganti ?activeTab= halaman --}}
<nav class="sipma-master-tabs" role="tablist">
    @foreach ($tabs as $tab)
        <button
            type="button"
            role="tab"
            wire:click="$set('activeTab', @js($tab['key']))"
            aria-selected="{{ $tab['active'] ? 'true' : 'false' }}"
            @class([
                'sipma-master-tab',
                'is-active' => $tab['active'],
                'has-tone sipma-tone-'.$tab['tone'] => filled($tab['tone']),
            ])
        >
            @if ($tab['icon'])
                <x-filament::icon :icon="$tab['icon']" class="sipma-master-tab-icon" />
            @endif
            <span>{{ $tab['label'] }}</span>
            @if (filled($tab['count']))
                <span class="sipma-master-tab-count">{{ \Illuminate\Support\Number::format($tab['count'], locale: app()->getLocale()) }}</span>
            @endif
        </button>
    @endforeach
</nav>
