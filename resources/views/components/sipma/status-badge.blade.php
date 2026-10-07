{{-- Label status (template): latar lembut, teks "-ink", titik berwarna dasar --}}
@props(['status'])

<span {{ $attributes->class(['sipma-status', 'sipma-tone-'.$status->getColor()]) }}>
    <span class="sipma-status-dot"></span>{{ $status->getLabel() }}
</span>
