{{-- Empty state kartu dashboard (template): ikon dalam lingkaran, judul, keterangan opsional --}}
@props(['icon', 'title', 'description' => null])

<div {{ $attributes->class('sipma-empty') }}>
    <span class="sipma-empty-icon">
        <x-filament::icon :icon="$icon" class="sipma-empty-svg" />
    </span>
    <p class="sipma-empty-title">{{ $title }}</p>
    @if ($description)
        <p class="sipma-empty-desc">{{ $description }}</p>
    @endif
</div>
