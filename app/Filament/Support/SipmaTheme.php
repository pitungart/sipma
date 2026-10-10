<?php

namespace App\Filament\Support;

use Filament\Enums\ThemeMode;
use Filament\FontProviders\GoogleFontProvider;
use Filament\Panel;
use Filament\Support\Enums\MaxWidth;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\View;

/**
 * Lapis tampilan SIPMA untuk seluruh panel Filament (admin, agen, mahasiswa), mengikuti
 * agents/Template Dashboard Lengkap (sipma-desing-rules.md v5.0). Dipakai ulang oleh portal (logo).
 */
final class SipmaTheme
{
    public const FONT = 'DM Sans';

    /**
     * Berkas logo resmi diletakkan manual di public/images; yang pertama ditemukan dipakai.
     */
    public const LOGO_CANDIDATES = [
        'images/logo-unud.svg',
        'images/logo-unud.png',
        'images/logo-unud.webp',
        'images/logo-unud.jpg',
        'images/kui-logo.svg',
    ];

    public static function logoPath(): ?string
    {
        foreach (self::LOGO_CANDIDATES as $path) {
            if (file_exists(public_path($path))) {
                return $path;
            }
        }

        return null;
    }

    /**
     * Shell, warna, font, dan elemen header yang sama untuk ketiga panel.
     */
    public static function apply(Panel $panel): Panel
    {
        return $panel
            ->colors(self::colors())
            ->font(self::FONT, provider: GoogleFontProvider::class) // sumber sama dengan portal
            ->darkMode() // tombol bulan/matahari di topbar + "Ganti tema" di menu profil
            ->defaultThemeMode(ThemeMode::Light) // template terang; gelap hanya bila dipilih
            ->brandLogo(fn (): View => view('filament.brand'))
            ->favicon(asset('favicon.png')) // logo Udayana, bukan ikon bawaan Laravel
            ->renderHook(PanelsRenderHook::HEAD_END, fn (): string => '<link rel="apple-touch-icon" href="'.asset('apple-touch-icon.png').'">')
            ->sidebarWidth('16rem') // 256px, sesuai template
            ->collapsedSidebarWidth('4.5rem') // 72px: rel ikon
            ->sidebarCollapsibleOnDesktop()
            ->maxContentWidth(MaxWidth::Full) // lebar penuh; padding tetap 32px diatur tema, terbuka maupun terlipat
            ->globalSearch(false)
            ->breadcrumbs() // breadcrumb di atas judul halaman, seperti template
            ->profile(isSimple: false)
            ->databaseNotifications()
            ->defaultAvatarProvider(InitialsAvatarProvider::class)
            ->viteTheme('resources/css/filament/admin/theme.css')
            // Kaki sidebar: kartu pengguna (avatar, nama, email)
            ->renderHook(PanelsRenderHook::SIDEBAR_FOOTER, fn (): View => view('filament.sidebar-user'))
            // Urutan kanan topbar: bahasa, tema, notifikasi (bawaan), menu profil
            ->renderHook(PanelsRenderHook::GLOBAL_SEARCH_AFTER, fn (): View => view('filament.topbar-actions'))
            ->renderHook(PanelsRenderHook::SIMPLE_PAGE_START, fn (): View => view('filament.language-switcher'));
    }

    /**
     * Skala indigo SIPMA. Warna merek #6466E9 ditaruh tepat di stop 600 karena Filament
     * memakai 600 sebagai isi tombol; Color::hex() akan menaruhnya di 500 lalu menggelapkan 600.
     *
     * @var array<int, string>
     */
    public const PRIMARY = [
        50 => '239, 239, 253', // --primary-l #EFEFFD
        100 => '228, 228, 252',
        200 => '206, 207, 249',
        300 => '169, 170, 248', // --primary-ink (gelap) #A9AAF8
        400 => '142, 144, 245', // --primary-h (gelap) #8E90F5
        500 => '124, 126, 242', // --primary (gelap) #7C7EF2
        600 => '100, 102, 233', // --primary #6466E9 — isi tombol, ring, state aktif
        700 => '75, 77, 214', // --primary-ink #4B4DD6
        800 => '62, 63, 176',
        900 => '50, 51, 140',
        950 => '31, 32, 84',
    ];

    /**
     * Netral template: abu kebiruan. 50–400 untuk mode terang, 700–950 untuk mode gelap
     * (950 = kanvas gelap, 900 = kartu gelap, 800 = garis gelap).
     *
     * @var array<int, string>
     */
    public const GRAY = [
        50 => '248, 249, 251', // --subtle #F8F9FB
        100 => '241, 242, 245', // --gray-l #F1F2F5
        200 => '231, 233, 239', // --border #E7E9EF
        300 => '217, 220, 228', // --border-2 #D9DCE4
        400 => '144, 148, 158', // --light #90949E
        500 => '109, 114, 127', // --muted #6D727F
        600 => '77, 85, 98', // --text #4D5562
        700 => '50, 57, 73', // --border-2 (gelap) #323949
        800 => '37, 43, 60', // --border (gelap) #252B3C
        900 => '22, 27, 43', // --card (gelap) #161B2B
        950 => '14, 18, 32', // --bg (gelap) #0E1220
    ];

    /**
     * Status: hijau (selesai), kuning (menunggu pembayaran), merah (revisi/gagal), cyan (diajukan).
     * Warna dasar template ada di stop 600, versi "-ink" di 700, latar "-l" di 50.
     *
     * @return array<string, array<int, string>>
     */
    public static function colors(): array
    {
        return [
            'primary' => self::PRIMARY,
            'gray' => self::GRAY,
            'success' => [
                50 => '238, 249, 240', 100 => '217, 242, 221', 200 => '184, 230, 191',
                300 => '134, 214, 144', 400 => '117, 204, 128', 500 => '104, 199, 115',
                600 => '94, 194, 106', 700 => '47, 138, 59', 800 => '39, 111, 49',
                900 => '33, 90, 41', 950 => '15, 50, 21',
            ],
            'warning' => [
                50 => '252, 247, 235', 100 => '248, 235, 203', 200 => '241, 216, 148',
                300 => '235, 200, 103', 400 => '230, 190, 82', 500 => '226, 185, 70',
                600 => '226, 181, 63', 700 => '148, 112, 15', 800 => '122, 92, 14',
                900 => '101, 76, 16', 950 => '58, 43, 6',
            ],
            'danger' => [
                50 => '252, 237, 237', 100 => '249, 218, 218', 200 => '243, 181, 179',
                300 => '240, 135, 130', 400 => '230, 106, 101', 500 => '226, 95, 90',
                600 => '221, 82, 76', 700 => '184, 58, 52', 800 => '150, 47, 42',
                900 => '122, 40, 36', 950 => '67, 18, 16',
            ],
            'info' => [
                50 => '237, 247, 250', 100 => '213, 238, 245', 200 => '176, 223, 236',
                300 => '127, 203, 226', 400 => '105, 192, 218', 500 => '93, 185, 213',
                600 => '82, 179, 209', 700 => '42, 127, 153', 800 => '35, 106, 128',
                900 => '31, 87, 106', 950 => '16, 52, 64',
            ],
        ];
    }
}
