{{-- Isi modal aksi: apa fungsi aksi ini, dan peringatan bila ada --}}
<div class="sipma-action-info">
    <x-sipma.notice>{{ $text }}</x-sipma.notice>

    @if (filled($warning ?? null))
        <x-sipma.notice tone="warning">{{ $warning }}</x-sipma.notice>
    @endif
</div>
