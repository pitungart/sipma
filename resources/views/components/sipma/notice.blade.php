{{-- Alert: kartu netral, ikon berwarna dalam bingkai tonal, garis tepi netral, judul + teks penjelas.
     Nada (ikon + bingkai): info = primary · peringatan/terkunci = warning · kabar baik = success ·
     masalah = danger. Isi slot = teks penjelas (boleh berisi daftar). --}}
@props(['tone' => 'info', 'icon' => null, 'title' => null, 'role' => 'note'])

@php
    $icon ??= match ($tone) {
        'warning' => 'lucide-triangle-alert',
        'success' => 'lucide-circle-check',
        'danger' => 'lucide-circle-alert',
        default => 'lucide-info',
    };

    // Info memakai warna primary (bukan biru info)
    $toneClass = 'sipma-tone-'.($tone === 'info' ? 'primary' : $tone);
@endphp

<div {{ $attributes->class(['sipma-alert sipma-section-alert', $toneClass]) }} role="{{ $role }}">
    <span class="sipma-alert-frame" aria-hidden="true">
        <x-filament::icon :icon="$icon" class="sipma-alert-icon" />
    </span>
    <div class="sipma-alert-body">
        @if (filled($title))
            <p class="sipma-alert-title">{{ $title }}</p>
        @endif
        @if ($slot->isNotEmpty())
            <div class="sipma-alert-text">{{ $slot }}</div>
        @endif
    </div>
</div>
