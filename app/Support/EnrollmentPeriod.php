<?php

namespace App\Support;

use App\Models\Student;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Bulan & tahun masuk pendaftar (pengganti periode akademik): pilihan isian dan labelnya,
 * mengikuti bahasa antarmuka.
 */
final class EnrollmentPeriod
{
    public const FIRST_YEAR = 2024;

    /**
     * @return array<int, string> 1 => "Januari", …
     */
    public static function months(): array
    {
        return collect(range(1, 12))
            ->mapWithKeys(fn (int $month): array => [$month => Carbon::create(2000, $month, 1)->translatedFormat('F')])
            ->all();
    }

    /**
     * Tahun pertama SIPMA sampai tahun depan (pendaftar bisa masuk tahun berikutnya).
     *
     * @return array<int, int>
     */
    public static function years(): array
    {
        $years = range(now()->year + 1, self::FIRST_YEAR);

        return array_combine($years, $years);
    }

    /**
     * "November 2026"; kosong bila belum diajukan dan belum diisi admin.
     */
    public static function label(Student $student): ?string
    {
        if ($student->enrollment_month === null || $student->enrollment_year === null) {
            return null;
        }

        return self::format($student->enrollment_year, $student->enrollment_month);
    }

    /**
     * Pilihan periode yang benar-benar ada pada mahasiswa di query ini, terbaru lebih dulu. Dengan
     * $countKey labelnya ikut menyebut jumlah mahasiswanya.
     *
     * @return array<string, string> "2026-09" => "September 2026"
     */
    public static function options(Builder $students, ?string $countKey = null): array
    {
        return (clone $students)
            ->whereNotNull('enrollment_year')
            ->whereNotNull('enrollment_month')
            ->toBase()
            ->select('enrollment_year', 'enrollment_month')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('enrollment_year', 'enrollment_month')
            ->orderByDesc('enrollment_year')
            ->orderByDesc('enrollment_month')
            ->get()
            ->mapWithKeys(function (object $row) use ($countKey): array {
                $period = self::format((int) $row->enrollment_year, (int) $row->enrollment_month);
                $key = sprintf('%04d-%02d', $row->enrollment_year, $row->enrollment_month);

                return [$key => $countKey ? trans_choice($countKey, (int) $row->total, ['period' => $period, 'count' => (int) $row->total]) : $period];
            })
            ->all();
    }

    /**
     * "2026-09" → [2026, 9]; null bila formatnya salah.
     *
     * @return array{0: int, 1: int}|null
     */
    public static function parse(string $key): ?array
    {
        return preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/', $key, $match) === 1
            ? [(int) $match[1], (int) $match[2]]
            : null;
    }

    private static function format(int $year, int $month): string
    {
        return Carbon::create($year, $month, 1)->translatedFormat('F Y');
    }
}
