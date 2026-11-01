{{-- Kepala dasbor mahasiswa (layout referensi): tanggal kapital, sapaan dengan nama berwarna primary, aksi di kanan --}}
<header class="sipma-page-head">
    <div class="sipma-min-0">
        <p class="sipma-portal-date">{{ $date }}</p>
        <h1 class="fi-header-heading sipma-page-title sipma-portal-greeting">
            {!! __('student.home.greeting', ['name' => '<span class="sipma-portal-name">'.e($name).'</span>']) !!}
            <span aria-hidden="true">👋</span>
        </h1>
        <p class="fi-header-subheading sipma-page-sub">{{ $subheading }}</p>
    </div>

    @if (filled($actions))
        <x-filament-actions::actions :actions="$actions" class="sipma-page-actions" />
    @endif
</header>
