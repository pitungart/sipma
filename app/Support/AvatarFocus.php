<?php

namespace App\Support;

/**
 * Posisi foto di dalam lingkaran avatar, tanpa memotong berkasnya.
 *
 * Fokus = titik foto (x, y; 0–1 dari lebar/tinggi foto) yang ditaruh di tengah lingkaran,
 * zoom 1–3, dan rasio foto (lebar/tinggi). Ukuran & letak foto dihitung dalam persen dari
 * lingkaran, jadi hasilnya sama di semua ukuran avatar. Rumus yang sama dipakai editor
 * (filament/pages/profile, Alpine) — ubah keduanya bersamaan.
 */
final class AvatarFocus
{
    public const MAX_ZOOM = 3;

    /**
     * @param  array<string, mixed>|null  $focus
     * @return array{x: float, y: float, zoom: float, ratio: float}|null
     */
    public static function normalize(?array $focus): ?array
    {
        if (! isset($focus['x'], $focus['y'], $focus['zoom'], $focus['ratio'])) {
            return null;
        }

        return [
            'x' => self::clamp((float) $focus['x'], 0, 1),
            'y' => self::clamp((float) $focus['y'], 0, 1),
            'zoom' => self::clamp((float) $focus['zoom'], 1, self::MAX_ZOOM),
            'ratio' => self::clamp((float) $focus['ratio'], 0.2, 5),
        ];
    }

    /**
     * Gaya inline untuk <img> di dalam .sipma-avatar-photo; kosong = tengah (object-fit: cover).
     *
     * @param  array<string, mixed>|null  $focus
     */
    public static function style(?array $focus): string
    {
        $focus = self::normalize($focus);

        if ($focus === null) {
            return '';
        }

        // Menutup lingkaran (cover), lalu diperbesar sesuai zoom
        $width = ($focus['ratio'] >= 1 ? $focus['ratio'] : 1) * 100 * $focus['zoom'];
        $height = ($focus['ratio'] >= 1 ? 1 : 1 / $focus['ratio']) * 100 * $focus['zoom'];

        // Titik fokus di tengah, tanpa menyisakan celah di tepi
        $left = self::clamp(50 - $focus['x'] * $width, 100 - $width, 0);
        $top = self::clamp(50 - $focus['y'] * $height, 100 - $height, 0);

        return sprintf('width:%.3F%%;height:%.3F%%;left:%.3F%%;top:%.3F%%;object-fit:fill', $width, $height, $left, $top);
    }

    private static function clamp(float $value, float $min, float $max): float
    {
        return max($min, min($max, $value));
    }
}
