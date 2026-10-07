<?php

namespace App\Support;

use App\Enums\NumberSegmentType;
use App\Enums\NumberType;
use App\Models\NumberFormat;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Pembuat nomor dinamis untuk semua jenis nomor (LOA, pendaftaran, MOU, kwitansi).
 *
 * Nomor = atribut berurutan (teks tetap, nomor urut, tahun, bulan) yang digabung satu pemisah,
 * mis. [LOA][SIPMA][Tahun 4 digit][Nomor urut 4 digit] + "/" → LOA/SIPMA/2026/0009.
 * Penghitung disimpan per jenis per periode (tahun / bulan / sepanjang waktu, dari atribut
 * nomor urut) dan dikunci saat diambil sehingga dua penerbitan bersamaan tidak mendapat nomor
 * yang sama. Mengubah susunan hanya memengaruhi nomor berikutnya.
 */
final class Numbering
{
    private const ROMAN = [1 => 'I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'];

    /**
     * Ambil nomor berikutnya dan majukan penghitungnya.
     */
    public static function next(NumberType $type, ?CarbonInterface $at = null): string
    {
        $at ??= now();
        $format = NumberFormat::for($type);
        $sequence = $format->sequence();
        $period = self::period($sequence['reset'], $at);

        return DB::transaction(function () use ($type, $format, $sequence, $period, $at): string {
            DB::table('number_sequences')->insertOrIgnore([
                'type' => $type->value, 'period' => $period, 'last_value' => 0,
                'created_at' => now(), 'updated_at' => now(),
            ]);

            $row = DB::table('number_sequences')
                ->where('type', $type->value)
                ->where('period', $period)
                ->lockForUpdate()
                ->first();

            [$value, $number] = self::firstFree($type, $format, max((int) $row->last_value + 1, $sequence['starts_at']), $at);

            DB::table('number_sequences')->where('id', $row->id)->update(['last_value' => $value, 'updated_at' => now()]);

            return $number;
        });
    }

    /**
     * Pratinjau nomor berikutnya tanpa memajukan penghitung. $format = susunan yang sedang
     * diedit di halaman pengaturan (belum disimpan); kosong = susunan tersimpan.
     */
    public static function preview(NumberType $type, ?NumberFormat $format = null): string
    {
        return self::upcoming($type, $format)[0]['number'];
    }

    /**
     * Beberapa nomor yang akan terbit berikutnya, tanpa memajukan penghitung (pratinjau &
     * simulasi). Setiap baris berisi nilai nomor urut dan nomor lengkapnya.
     *
     * @return list<array{value: int, number: string}>
     */
    public static function upcoming(NumberType $type, ?NumberFormat $format = null, int $count = 1): array
    {
        $at = now();
        $format ??= NumberFormat::for($type);
        $sequence = $format->sequence();
        $value = max(1 + (int) DB::table('number_sequences')
            ->where('type', $type->value)
            ->where('period', self::period($sequence['reset'], $at))
            ->value('last_value'), $sequence['starts_at']);

        $rows = [];

        for ($i = 0; $i < max(1, $count); $i++) {
            [$value, $number] = self::firstFree($type, $format, $value, $at);
            $rows[] = ['value' => $value, 'number' => $number];
            $value++;
        }

        return $rows;
    }

    public static function render(NumberFormat $format, int $sequence, CarbonInterface $at): string
    {
        return collect($format->segments)
            ->map(fn (array $segment): string => self::part($segment, $sequence, $at))
            ->implode($format->separator->character());
    }

    /**
     * Hasil satu atribut, mis. tahun 2 digit → 26, nomor urut 4 digit → 0009.
     *
     * @param  array<string, mixed>  $segment
     */
    public static function part(array $segment, int $sequence, CarbonInterface $at): string
    {
        return match (NumberSegmentType::tryFrom((string) ($segment['type'] ?? ''))) {
            NumberSegmentType::Sequence => str_pad((string) $sequence, max(1, (int) ($segment['length'] ?? 4)), '0', STR_PAD_LEFT),
            NumberSegmentType::Year => (int) ($segment['digits'] ?? 4) === 2 ? $at->format('y') : $at->format('Y'),
            NumberSegmentType::Month => ($segment['style'] ?? 'number') === 'roman' ? self::ROMAN[$at->month] : $at->format('m'),
            default => trim((string) ($segment['value'] ?? '')),
        };
    }

    /**
     * Nomor urut pertama mulai $value yang hasilnya belum pernah terbit (mis. susunan
     * dikembalikan ke format lama), agar nomor tidak pernah terbit dua kali.
     *
     * @return array{0: int, 1: string}
     */
    private static function firstFree(NumberType $type, NumberFormat $format, int $value, CarbonInterface $at): array
    {
        [$table, $column] = $type->column();

        while (DB::table($table)->where($column, $number = self::render($format, $value, $at))->exists()) {
            $value++;
        }

        return [$value, $number];
    }

    private static function period(string $reset, CarbonInterface $at): string
    {
        return match ($reset) {
            'monthly' => $at->format('Y-m'),
            'never' => 'all',
            default => $at->format('Y'),
        };
    }
}
