@use('App\Support\Locale')

<nav aria-label="{{ __('portal.language.label') }}" {{ $attributes->class('inline-flex items-center gap-1 rounded-control bg-muted p-1') }}>
    @foreach (Locale::SUPPORTED as $code)
        @php($isActive = app()->getLocale() === $code)

        {{-- R-3.10: target sentuh 44px --}}
        <a
            href="{{ route('locale.switch', $code) }}"
            hreflang="{{ $code }}"
            lang="{{ $code }}"
            @if ($isActive) aria-current="true" @endif
            @class([
                'sipma-focus inline-flex min-h-11 min-w-11 items-center justify-center rounded-chip px-3 text-small font-medium transition-colors',
                'bg-canvas text-ink shadow-card' => $isActive,
                'text-ink-muted hover:text-ink' => ! $isActive,
            ])
        >
            <span aria-hidden="true">{{ strtoupper($code) }}</span>
            <span class="sr-only">{{ __('portal.language.'.$code) }}</span>
        </a>
    @endforeach
</nav>
