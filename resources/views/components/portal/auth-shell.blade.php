{{--
    Kerangka halaman autentikasi portal (register & login), pengecualian §2.5 sipma-desing-rules.md.
    Kanan (desktop): konten formulir dari $slot di latar putih. Kiri: identitas dengan gradien pastel bergrain.
--}}
@use('App\Filament\Support\SipmaTheme')

@php
    $logo = SipmaTheme::logoPath();
@endphp

<div class="min-h-screen bg-card lg:grid lg:grid-cols-2">
    {{-- Formulir di latar putih. Di DOM tetap pertama (urutan fokus & pembaca layar); di desktop tampil di kanan. --}}
    <div class="flex min-h-screen flex-col bg-card px-6 py-4 sm:px-10 lg:order-last lg:px-16">
        {{-- Header hanya di mobile: identitas + pemilih bahasa. Di desktop keduanya ada di baris atas panel kiri. --}}
        <header class="flex items-center justify-between gap-4 lg:hidden">
            <div class="flex min-w-0 items-center gap-3">
                @if ($logo)
                    <img src="{{ asset($logo) }}" alt="{{ __('portal.brand.logo_alt') }}" class="h-10 w-auto shrink-0">
                @endif
                <p class="text-h3 font-semibold text-ink">SIPMA</p>
            </div>

            <x-language-switcher />
        </header>

        {{-- Mobile: mulai dari atas; desktop: formulir ditengahkan vertikal --}}
        <main id="main" tabindex="-1" class="flex flex-1 items-start justify-center py-6 focus:outline-none lg:items-center lg:py-8">
            <div class="w-full max-w-lg">
                {{ $slot }}
            </div>
        </main>

        <footer class="text-caption text-ink-subtle">
            {{ __('portal.footer.copyright', ['year' => now()->year]) }}
        </footer>
    </div>

    {{-- Identitas: gradien pastel + blur + grain, sticky setinggi layar; disembunyikan di bawah lg. --}}
    <aside class="sipma-auth-bg relative isolate hidden overflow-hidden lg:sticky lg:top-0 lg:order-first lg:flex lg:h-screen lg:flex-col lg:items-center lg:justify-center lg:px-12 lg:pb-10 lg:pt-28" aria-labelledby="portal-identity">
        {{-- Bulatan warna bergerak pelan dengan irama berbeda (pengecualian R-2.19; mati untuk reduce motion) --}}
        <div class="sipma-blob sipma-drift-a -left-24 -top-24 h-[28rem] w-[28rem] bg-primary-200/70" aria-hidden="true"></div>
        <div class="sipma-blob sipma-drift-b -bottom-32 -right-24 h-[30rem] w-[30rem] bg-primary-300/50" aria-hidden="true"></div>
        <div class="sipma-blob sipma-drift-c left-[20%] top-[18%] h-[16rem] w-[16rem] bg-gold/35" aria-hidden="true"></div>
        <div class="sipma-grain" aria-hidden="true"></div>

        {{-- Baris atas: lockup institusi di kiri (kapital, weight 600; pengecualian R-2.14 di §2.5), pemilih bahasa di kanan --}}
        <div class="absolute inset-x-10 top-8 flex items-center justify-between gap-6 xl:inset-x-12">
            <div class="flex min-w-0 items-center gap-4">
                @if ($logo)
                    <img src="{{ asset($logo) }}" alt="{{ __('portal.brand.logo_alt') }}" class="h-14 w-auto shrink-0">
                @endif
                <p class="text-body font-semibold uppercase leading-snug tracking-wide text-ink">
                    <span class="block">{{ __('portal.brand.office_name') }}</span>
                    <span class="block">{{ __('portal.brand.university') }}</span>
                </p>
            </div>

            <x-language-switcher class="shrink-0" />
        </div>

        {{--
            Hero: ilustrasi mahasiswa (latar transparan) di atas grid garis putus-putus yang memudar.
            Teks di dalam gambar tidak bisa diterjemahkan/dibaca pembaca layar, jadi dijelaskan lewat alt.
        --}}
        <div class="relative flex min-h-0 w-full max-w-xl justify-center">
            <div class="sipma-dashed-grid -inset-x-10 -inset-y-8" aria-hidden="true"></div>
            <img
                src="{{ asset('images/students-illustration.webp') }}"
                alt="{{ __('portal.brand.illustration_alt') }}"
                width="1200"
                height="800"
                decoding="async"
                fetchpriority="high"
                class="sipma-float relative h-auto max-h-[48vh] w-auto max-w-full object-contain"
            >
        </div>

        {{-- Nama sistem + deskripsi portal (identitas unit ada di lockup kiri atas) --}}
        <div class="relative mt-6 w-full max-w-xl text-center">
            <h2 id="portal-identity">
                <span class="block text-display tracking-tight text-primary-dark">SIPMA</span>
                <span class="mt-2 block text-balance text-h2 text-ink">{{ __('portal.brand.system_name') }}</span>
            </h2>

            <span class="mx-auto mt-4 block h-1 w-12 rounded-full bg-gold" aria-hidden="true"></span>

            <p class="mx-auto mt-4 max-w-md text-balance text-body text-ink-muted">{{ __('portal.brand.tagline') }}</p>
        </div>
    </aside>
</div>
