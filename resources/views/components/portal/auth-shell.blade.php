{{--
    Kerangka halaman autentikasi portal (register & login), pengecualian §2.5 sipma-desing-rules.md.
    Kanan (desktop): konten formulir dari $slot di latar putih. Kiri: identitas dengan gradien pastel bergrain.
--}}
@use('App\Filament\Support\SipmaTheme')

@php
    $logo = SipmaTheme::logoPath();

    // Tiga cincin orbit berputar dengan kecepatan & arah berbeda.
    // Blok @php harus mendahului semua @php(...) satu baris di file ini (keterbatasan kompilasi Blade).
    $orbitRings = [
        ['radius' => 12, 'inset' => 'inset-0', 'border' => 'border border-primary-200', 'duration' => 90, 'reverse' => false],
        ['radius' => 9, 'inset' => 'inset-12', 'border' => 'border border-primary-200/80', 'duration' => 70, 'reverse' => true],
        ['radius' => 6, 'inset' => 'inset-24', 'border' => 'border border-dashed border-gold', 'duration' => 50, 'reverse' => false],
    ];

    // Posisi awal ikon & titik di tiap cincin: sudut (derajat) dan jari-jari cincin (rem)
    $orbitIcons = [
        ['icon' => 'heroicon-o-academic-cap', 'angle' => -38, 'radius' => 12],
        ['icon' => 'heroicon-o-globe-asia-australia', 'angle' => 112, 'radius' => 12],
        ['icon' => 'heroicon-o-document-text', 'angle' => 158, 'radius' => 9],
    ];

    $orbitDots = [
        ['angle' => 232, 'radius' => 12, 'size' => 'h-2 w-2', 'color' => 'bg-primary-300'],
        ['angle' => 8, 'radius' => 12, 'size' => 'h-1.5 w-1.5', 'color' => 'bg-primary-400'],
        ['angle' => 42, 'radius' => 9, 'size' => 'h-2.5 w-2.5', 'color' => 'bg-gold'],
        ['angle' => 300, 'radius' => 6, 'size' => 'h-2 w-2', 'color' => 'bg-gold'],
    ];
@endphp

<div class="min-h-screen bg-canvas lg:grid lg:grid-cols-2">
    {{-- Formulir di latar putih. Di DOM tetap pertama (urutan fokus & pembaca layar); di desktop tampil di kanan. --}}
    <div class="flex min-h-screen flex-col bg-canvas px-6 py-4 sm:px-10 lg:order-last lg:px-16">
        <header class="flex items-center justify-between gap-4 lg:justify-end">
            {{-- Identitas hanya di mobile; di desktop sudah ada di sisi kiri --}}
            <div class="flex min-w-0 items-center gap-3 lg:hidden">
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
    <aside class="sipma-auth-bg relative isolate hidden overflow-hidden lg:sticky lg:top-0 lg:order-first lg:flex lg:h-screen lg:flex-col lg:items-center lg:justify-center lg:px-12" aria-labelledby="portal-identity">
        <div class="sipma-blob -left-24 -top-24 h-[28rem] w-[28rem] bg-primary-200/70" aria-hidden="true"></div>
        <div class="sipma-blob -bottom-32 -right-24 h-[30rem] w-[30rem] bg-primary-300/50" aria-hidden="true"></div>
        <div class="sipma-blob left-[20%] top-[18%] h-[16rem] w-[16rem] bg-gold/35" aria-hidden="true"></div>
        <div class="sipma-grain" aria-hidden="true"></div>

        {{-- Ilustrasi orbit: logo di pusat tanpa latar, tiga cincin berputar pelan (ikon tetap tegak) --}}
        <div class="relative grid h-[24rem] w-[24rem] shrink-0 place-items-center" aria-hidden="true">
            @foreach ($orbitRings as $ring)
                <div
                    @class(['sipma-orbit-ring absolute inset-0', 'sipma-orbit-reverse' => $ring['reverse']])
                    style="--orbit-duration: {{ $ring['duration'] }}s"
                >
                    <span class="absolute {{ $ring['inset'] }} rounded-full {{ $ring['border'] }}"></span>

                    @foreach (collect($orbitDots)->where('radius', $ring['radius']) as $dot)
                        <span
                            class="absolute left-1/2 top-1/2 rounded-full {{ $dot['size'] }} {{ $dot['color'] }}"
                            style="transform: translate(-50%, -50%) rotate({{ $dot['angle'] }}deg) translateX({{ $dot['radius'] }}rem)"
                        ></span>
                    @endforeach

                    @foreach (collect($orbitIcons)->where('radius', $ring['radius']) as $item)
                        <span
                            class="absolute left-1/2 top-1/2"
                            style="transform: translate(-50%, -50%) rotate({{ $item['angle'] }}deg) translateX({{ $item['radius'] }}rem) rotate({{ -$item['angle'] }}deg)"
                        >
                            <span class="sipma-glass-grain sipma-orbit-upright grid h-11 w-11 place-items-center rounded-control text-primary">
                                @svg($item['icon'], 'h-5 w-5')
                            </span>
                        </span>
                    @endforeach
                </div>
            @endforeach

            @if ($logo)
                <img src="{{ asset($logo) }}" alt="" class="relative h-40 w-40 object-contain">
            @endif
        </div>

        {{-- Urutan: logo (di atas) → nama sistem → deskripsi --}}
        <div class="relative mt-12 w-full max-w-2xl text-center">
            <h2 id="portal-identity">
                <span class="block text-display tracking-tight text-primary">SIPMA</span>
                <span class="mt-2 block text-balance text-h2 text-ink">{{ __('portal.brand.system_name') }}</span>
            </h2>

            <span class="mx-auto mt-4 block h-1 w-12 rounded-full bg-gold" aria-hidden="true"></span>

            {{-- Deskripsi unit ditonjolkan lewat ukuran & warna (16px, primary), tanpa latar/border --}}
            <p class="mt-5 inline-flex items-center gap-2 text-body font-medium text-primary">
                @svg('heroicon-o-building-library', 'h-5 w-5 shrink-0', ['aria-hidden' => 'true'])
                {{ __('portal.brand.office') }}
            </p>
        </div>
    </aside>
</div>
