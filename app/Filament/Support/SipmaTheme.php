<?php

namespace App\Filament\Support;

use Filament\FontProviders\GoogleFontProvider;
use Filament\Panel;
use Filament\Support\Colors\Color;

/**
 * Lapis brand SIPMA (.agents/sipma-desing-rules.md v3.0 §1–§2, R-4.1) untuk seluruh panel Filament
 * dan dipakai ulang oleh portal (logo).
 */
final class SipmaTheme
{
    public const FONT = 'Inter';

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

    public static function apply(Panel $panel): Panel
    {
        $panel
            ->colors(self::colors())
            ->font(self::FONT, provider: GoogleFontProvider::class) // sumber sama dengan portal
            ->darkMode(false); // R-2.18

        $logo = self::logoPath();

        return $logo
            ? $panel->brandLogo(fn (): string => asset($logo))->brandLogoHeight('2.25rem')
            : $panel->brandName('SIPMA');
    }

    /**
     * R-4.1. Emas tidak didaftarkan sebagai warna semantik (R-4.2).
     *
     * @return array<string, array<int, string>>
     */
    public static function colors(): array
    {
        return [
            'primary' => Color::hex('#192676'),
            'success' => Color::hex('#15803D'),
            'danger' => Color::hex('#B91C1C'),
            'info' => Color::hex('#2B3DAB'), // --color-pending: menunggu verifikasi KUI (R-1.8)
            'gray' => Color::Zinc, // netral tanpa rona (R-1.3)
        ];
    }
}
