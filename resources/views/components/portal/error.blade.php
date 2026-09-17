@props(['id', 'message'])

{{-- R-3.4: pesan tepat di bawah field; ikon + teks, bukan warna saja (R-1.9) --}}
<p id="{{ $id }}" class="mt-1 flex items-start gap-2 text-small text-danger">
    @svg('heroicon-m-exclamation-circle', 'mt-0.5 h-4 w-4 shrink-0', ['aria-hidden' => 'true'])
    <span><span class="sr-only">{{ __('portal.register.error_prefix') }} </span>{{ $message }}</span>
</p>
