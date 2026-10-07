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
    /**
     * Lima pasangan latar + teks avatar dari template; dipilih stabil berdasarkan nama.
     */
    public const TONES = ['primary', 'info', 'success', 'warning', 'danger'];

    public function get(Model $record): string
    {
        $initials = self::initialsOf(Filament::getNameForDefaultAvatar($record));

        $svg = <<<SVG
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64">
                <rect width="64" height="64" rx="32" fill="#EFEFFD"/>
                <text x="32" y="33" fill="#4B4DD6" font-family="'DM Sans', system-ui, sans-serif"
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
     * Nada warna avatar untuk sebuah nama — sama di setiap muat ulang halaman.
     */
    public static function toneOf(string $name): string
    {
        return self::TONES[crc32($name) % count(self::TONES)];
    }
}
