{{-- Kepala dashboard (template): tanggal kecil di atas sapaan, aksi di kanan --}}
<header class="sipma-page-head">
    <div>
        <p class="sipma-page-kicker">{{ $date }}</p>
        <h1 class="fi-header-heading sipma-page-title">{{ $greeting }}</h1>
    </div>

    @if (filled($actions))
        <x-filament-actions::actions :actions="$actions" class="sipma-page-actions" />
    @endif
</header>
