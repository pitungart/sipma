{{-- Tab antar entitas master data di kepala kartu tabel (template): ikon, label, jumlah --}}
<nav class="sipma-master-tabs" aria-label="{{ __('admin.groups.master_data') }}">
    @foreach ($tabs as $tab)
        <a
            href="{{ $tab['url'] }}"
            @class(['sipma-master-tab', 'is-active' => $tab['active']])
            @if ($tab['active']) aria-current="page" @endif
        >
            <x-filament::icon :icon="$tab['icon']" class="sipma-master-tab-icon" />
            <span>{{ $tab['label'] }}</span>
            <span class="sipma-master-tab-count">{{ \Illuminate\Support\Number::format($tab['count'], locale: app()->getLocale()) }}</span>
        </a>
    @endforeach
</nav>
