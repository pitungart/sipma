{{-- Pesan di dalam kartu/tab: alert tonal berikon, nada sesuai arti (keputusan 26 Oktober 2026 — bukan
     kotak abu-abu). info = info · peringatan/terkunci = warning · kabar baik = success · masalah = danger. --}}
@props(['tone' => 'info', 'icon' => null])

@php
    $icon ??= match ($tone) {
        'warning' => 'lucide-triangle-alert',
        'success' => 'lucide-circle-check',
        'danger' => 'lucide-circle-alert',
        default => 'lucide-info',
    };
@endphp

<div {{ $attributes->class(['sipma-alert sipma-section-alert', 'sipma-tone-'.$tone]) }} role="note">
    <x-filament::icon :icon="$icon" class="sipma-alert-icon" />
    <div>{{ $slot }}</div>
</div>
