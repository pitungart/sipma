<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Bahasa antarmuka portal dan panel agen/mahasiswa.
 *
 * Urutan penentuan: pilihan manual (session, lalu cookie) → bahasa browser → English.
 */
final class Locale
{
    public const SUPPORTED = ['en', 'id'];

    public const DEFAULT = 'en';

    public const SESSION_KEY = 'locale';

    public const COOKIE = 'locale';

    public const COOKIE_MINUTES = 60 * 24 * 365;

    public static function isSupported(mixed $locale): bool
    {
        return is_string($locale) && in_array($locale, self::SUPPORTED, true);
    }

    public static function resolve(Request $request): string
    {
        $chosen = [
            $request->hasSession() ? $request->session()->get(self::SESSION_KEY) : null,
            $request->cookie(self::COOKIE),
        ];

        foreach ($chosen as $locale) {
            if (self::isSupported($locale)) {
                return $locale;
            }
        }

        return $request->getPreferredLanguage(self::SUPPORTED) ?? self::DEFAULT;
    }
}
