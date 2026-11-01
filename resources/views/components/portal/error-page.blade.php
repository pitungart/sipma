{{--
    Kerangka halaman error (404, dan kode lain bila ditambah): latar & identitas sama dengan
    halaman login (§2.5) agar tidak terasa "keluar" dari SIPMA. Tombol utama ke dasbor panel
    bila sudah masuk, ke login bila tamu; tombol kembali hanya bila ada halaman sebelumnya.
--}}
@use('App\Filament\Support\SipmaTheme')

@props([
    'code',
    'title',
    'message',
])

@php
    $logo = SipmaTheme::logoPath();
    $user = auth()->user();
    // previous() jatuh ke beranda bila tidak ada riwayat; beranda hanya redirect, jadi tidak dihitung
    $previous = url()->previous();
    $canGoBack = ! in_array($previous, [url()->current(), url('/')], true) && str_starts_with($previous, url('/'));
@endphp

<x-layouts.portal :title="$title">
    <div class="sipma-auth-bg relative isolate flex min-h-screen flex-col overflow-hidden px-6 py-4 sm:px-10 sm:py-6">
        <div class="sipma-blob sipma-drift-a -left-24 -top-24 h-[28rem] w-[28rem] bg-primary-200/70" aria-hidden="true"></div>
        <div class="sipma-blob sipma-drift-b -bottom-32 -right-24 h-[30rem] w-[30rem] bg-primary-300/50" aria-hidden="true"></div>
        <div class="sipma-blob sipma-drift-c left-[55%] top-[12%] h-[14rem] w-[14rem] bg-gold/35" aria-hidden="true"></div>
        <div class="sipma-grain" aria-hidden="true"></div>

        <header class="flex items-center justify-between gap-4">
            <a href="{{ url('/') }}" class="sipma-focus flex min-w-0 items-center gap-3 rounded-control">
                @if ($logo)
                    <img src="{{ asset($logo) }}" alt="{{ __('portal.brand.logo_alt') }}" class="h-10 w-auto shrink-0 sm:h-12">
                @endif
                <span class="min-w-0">
                    <span class="block text-h3 font-semibold text-ink">SIPMA</span>
                    <span class="hidden truncate text-caption text-ink-muted sm:block">{{ __('portal.brand.office') }}</span>
                </span>
            </a>

            <x-language-switcher class="shrink-0" />
        </header>

        <main id="main" tabindex="-1" class="flex flex-1 items-center justify-center py-10 focus:outline-none">
            <div class="w-full max-w-lg text-center">
                {{-- Angka besar dekoratif; kodenya dibacakan lewat kicker di bawah --}}
                <div class="relative mx-auto flex justify-center" aria-hidden="true">
                    <div class="sipma-dashed-grid -inset-x-16 -inset-y-10"></div>
                    <span class="sipma-error-code relative">{{ $code }}</span>
                </div>

                <span class="mx-auto mt-4 block h-1 w-12 rounded-full bg-gold" aria-hidden="true"></span>

                <p class="mt-6 text-small font-medium text-primary-link">{{ __('portal.errors.kicker', ['code' => $code]) }}</p>
                <h1 class="mt-1 text-balance text-h1 text-ink sm:text-display">{{ $title }}</h1>
                <p class="mx-auto mt-3 max-w-md text-balance text-body text-ink-muted">{{ $message }}</p>

                {{ $slot }}

                <div class="mt-8 flex flex-col-reverse items-stretch justify-center gap-3 sm:flex-row sm:items-center">
                    @if ($canGoBack)
                        <a href="{{ $previous }}" class="sipma-button-secondary">
                            @svg('lucide-arrow-left', 'h-5 w-5', ['aria-hidden' => 'true'])
                            {{ __('portal.errors.back') }}
                        </a>
                    @endif

                    @if ($user)
                        <a href="{{ $user->panelUrl() }}" class="sipma-button">
                            @svg('lucide-house', 'h-5 w-5', ['aria-hidden' => 'true'])
                            {{ __('portal.errors.dashboard') }}
                        </a>
                    @else
                        <a href="{{ route('login') }}" class="sipma-button">
                            @svg('lucide-log-in', 'h-5 w-5', ['aria-hidden' => 'true'])
                            {{ __('portal.errors.login') }}
                        </a>
                    @endif
                </div>
            </div>
        </main>

        <footer class="text-center text-caption text-ink-subtle sm:text-start">
            {{ __('portal.footer.copyright', ['year' => now()->year]) }}
        </footer>
    </div>
</x-layouts.portal>
