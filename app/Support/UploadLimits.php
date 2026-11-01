<?php

namespace App\Support;

use App\Enums\DocumentType;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Number;

/**
 * Batas ukuran unggahan (Sistem → Unggahan berkas, keputusan 26 Oktober 2026): tiap jenis dokumen
 * pendaftar, bukti pembayaran, dan berkas MOU agen diatur terpisah oleh Super Admin. Nilai dalam KB;
 * belum diatur = nilai bawaan. Dibaca dari cache; disimpan lewat save().
 *
 * Nama batas = nilai DocumentType untuk dokumen, ditambah PAYMENT_PROOF dan MOU.
 */
final class UploadLimits
{
    public const PAYMENT_PROOF = 'payment_proof';

    public const MOU = 'mou';

    public const DOCUMENT_DEFAULT = 1024;

    public const PAYMENT_PROOF_DEFAULT = 2048;

    public const MOU_DEFAULT = 5120;

    /** Pilihan cepat di halaman pengaturan; nilai lain diisi sebagai "Custom" */
    public const PRESETS = [300, 500, 1024, 2048, 5120, 10240];

    public const MIN_KB = 100;

    /** Di bawah batas unggahan sementara Livewire (12 MB) */
    public const MAX_KB = 10240;

    /** Batas lama (satu nilai untuk semua dokumen), dipakai bila jenis dokumen belum diatur sendiri */
    private const LEGACY_DOCUMENT = 'document';

    private const CACHE_KEY = 'settings.upload_limits';

    /**
     * Nilai bawaan per nama batas, urut seperti di halaman pengaturan.
     *
     * @return array<string, int>
     */
    public static function defaults(): array
    {
        $defaults = [];
        foreach (DocumentType::cases() as $type) {
            $defaults[$type->value] = self::DOCUMENT_DEFAULT;
        }

        return $defaults + [self::PAYMENT_PROOF => self::PAYMENT_PROOF_DEFAULT, self::MOU => self::MOU_DEFAULT];
    }

    public static function document(DocumentType $type): int
    {
        return self::all()[$type->value];
    }

    public static function paymentProof(): int
    {
        return self::all()[self::PAYMENT_PROOF];
    }

    public static function mou(): int
    {
        return self::all()[self::MOU];
    }

    /**
     * @return array<string, int>
     */
    public static function all(): array
    {
        $saved = Cache::rememberForever(self::CACHE_KEY, fn (): array => Setting::query()
            ->where('key', 'like', self::settingKey('%'))
            ->pluck('value', 'key')
            ->all());

        $legacy = $saved[self::settingKey(self::LEGACY_DOCUMENT)] ?? null;

        $limits = [];
        foreach (self::defaults() as $name => $default) {
            $fallback = DocumentType::tryFrom($name) !== null ? ($legacy ?? $default) : $default;
            $limits[$name] = (int) ($saved[self::settingKey($name)] ?? $fallback);
        }

        return $limits;
    }

    /**
     * @param  array<string, int|string>  $limits  nama => KB
     */
    public static function save(array $limits): void
    {
        foreach (array_intersect_key($limits, self::defaults()) as $name => $kb) {
            Setting::query()->updateOrCreate(['key' => self::settingKey($name)], ['value' => (string) (int) $kb]);
        }

        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Ketentuan berkas MOU (agent.mou.rules.items) dengan batas ukuran yang berlaku.
     *
     * @return list<string>
     */
    public static function mouRules(): array
    {
        return array_map(
            fn (string $rule): string => str_replace(':max', self::label(self::mou()), $rule),
            __('agent.mou.rules.items'),
        );
    }

    /**
     * Ukuran untuk dibaca orang: "1 MB", "1,5 MB", "300 KB".
     */
    public static function label(int $kb): string
    {
        return $kb >= 1024
            ? Number::format($kb / 1024, maxPrecision: 1, locale: app()->getLocale()).' MB'
            : $kb.' KB';
    }

    /**
     * Batas server PHP (upload_max_filesize & post_max_size, yang lebih kecil) dalam KB;
     * berkas di atasnya ditolak server sebelum sampai ke aplikasi.
     */
    public static function serverMaxKb(): int
    {
        $toKb = function (string $value): int {
            $number = (float) $value;

            return (int) match (strtoupper(substr(trim($value), -1))) {
                'G' => $number * 1024 * 1024,
                'M' => $number * 1024,
                'K' => $number,
                default => $number / 1024,
            };
        };

        $limits = array_filter([$toKb((string) ini_get('upload_max_filesize')), $toKb((string) ini_get('post_max_size'))]);

        return $limits === [] ? PHP_INT_MAX : min($limits);
    }

    private static function settingKey(string $name): string
    {
        return "upload_max_kb.{$name}";
    }
}
