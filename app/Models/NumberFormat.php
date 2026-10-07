<?php

namespace App\Models;

use App\Enums\NumberSegmentType;
use App\Enums\NumberSeparator;
use App\Enums\NumberType;
use Illuminate\Database\Eloquent\Model;

/**
 * Susunan satu jenis nomor (Sistem → Penomoran): atribut berurutan digabung satu pemisah.
 * Baris belum ada = pakai susunan bawaan NumberType::defaultFormat().
 *
 * Atribut (segments) berbentuk ['type' => NumberSegmentType, ...isian tipe itu]:
 * text → value · sequence → length, reset, starts_at · year → digits · month → style.
 */
class NumberFormat extends Model
{
    public const RESETS = ['yearly', 'monthly', 'never'];

    public const MONTH_STYLES = ['number', 'roman'];

    public const MAX_SEGMENTS = 8;

    protected $fillable = [
        'type',
        'separator',
        'segments',
    ];

    protected function casts(): array
    {
        return [
            'type' => NumberType::class,
            'separator' => NumberSeparator::class,
            'segments' => 'array',
        ];
    }

    /**
     * Susunan tersimpan, atau susunan bawaan (belum disimpan) bila belum ada di database.
     */
    public static function for(NumberType $type): self
    {
        return static::query()->firstWhere('type', $type) ?? new static(['type' => $type] + $type->defaultFormat());
    }

    /**
     * Atribut nomor urut — sumber panjang, reset, dan nilai mulai penghitung.
     *
     * @return array{length: int, reset: string, starts_at: int}
     */
    public function sequence(): array
    {
        $segment = collect($this->segments)->firstWhere('type', NumberSegmentType::Sequence->value)
            ?? NumberSegmentType::Sequence->defaults();

        return [
            'length' => max(1, (int) ($segment['length'] ?? 4)),
            'reset' => in_array($segment['reset'] ?? null, self::RESETS, true) ? $segment['reset'] : 'yearly',
            'starts_at' => max(1, (int) ($segment['starts_at'] ?? 1)),
        ];
    }

    /**
     * Rapikan atribut dari form: urutan 0..n dan hanya isian milik tipenya.
     *
     * @param  iterable<array<string, mixed>>  $segments
     * @return list<array<string, mixed>>
     */
    public static function normalize(iterable $segments): array
    {
        return collect($segments)->values()->map(function (array $segment): array {
            $type = NumberSegmentType::tryFrom((string) ($segment['type'] ?? '')) ?? NumberSegmentType::Text;
            $defaults = $type->defaults();

            return collect($defaults)
                ->map(fn (mixed $default, string $key): mixed => $key === 'type' ? $type->value : ($segment[$key] ?? $default))
                ->map(fn (mixed $value, string $key): mixed => match ($key) {
                    'value' => trim((string) $value),
                    'length', 'starts_at', 'digits' => (int) $value,
                    default => $value,
                })
                ->all();
        })->all();
    }
}
