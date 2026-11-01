{{-- Kepala katalog (layout referensi): judul + deskripsi di kiri, pencarian di kanan.
     Kata kunci dikirim lewat event ke grid kartu (penyaringan di browser, tanpa kueri baru). --}}
<header class="sipma-page-head">
    <div class="sipma-min-0">
        <h1 class="fi-header-heading sipma-page-title">{{ $heading }}</h1>
        <p class="fi-header-subheading sipma-page-sub">{{ $subheading }}</p>
    </div>

    @if ($searchable)
        <label class="sipma-portal-search">
            <span class="sr-only">{{ __('student.programs.search') }}</span>
            <x-filament::icon icon="lucide-search" class="sipma-portal-search-icon" />
            <input
                type="search"
                placeholder="{{ __('student.programs.search') }}"
                x-data
                x-on:input.debounce.150ms="$dispatch('sipma-program-search', $event.target.value)"
            />
        </label>
    @endif
</header>
