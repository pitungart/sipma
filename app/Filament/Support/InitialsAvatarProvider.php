<?php

namespace App\Filament\Support;

use Filament\AvatarProviders\Contracts\AvatarProvider;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;

/**
 * Avatar inisial yang dirender sendiri sebagai SVG data URI.
 *
 * Penyedia bawaan Filament (ui-avatars.com) mengirim nama pengguna ke layanan pihak ketiga
 * lewat URL gambar. SIPMA memproses data pribadi mahasiswa asing dan staf, jadi nama itu
 * tidak dikirim ke luar (R-4.1g).
 */
class InitialsAvatarProvider implements AvatarProvider
{
    public function get(Model $record): string
    {
        $initials = self::initialsOf(Filament::getNameForDefaultAvatar($record));
        $hue = self::hueOf($record->getKey());

        // Tonal seperti x-sipma.avatar (mode terang): latar lembut + teks pekat dari hue yang sama
        $svg = <<<SVG
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64">
                <rect width="64" height="64" rx="32" fill="hsl({$hue} 70% 94%)"/>
                <text x="32" y="33" fill="hsl({$hue} 50% 36%)" font-family="'DM Sans', system-ui, sans-serif"
                      font-size="24" font-weight="700" text-anchor="middle"
                      dominant-baseline="central">{$initials}</text>
            </svg>
            SVG;

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    public static function initialsOf(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return collect($parts)
            ->take(2)
            ->map(fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('');
    }

    /**
     * Hue warna avatar (0–359) dari ID record: tiap orang dapat warna sendiri yang tetap sama
     * di setiap halaman dan tidak berubah walau namanya diganti.
     */
    public static function hueOf(int|string|null $id): int
    {
        return crc32((string) $id) % 360;
    }
}
