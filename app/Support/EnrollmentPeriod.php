<?php

namespace App\Support;

use App\Models\Student;
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

        return Carbon::create($student->enrollment_year, $student->enrollment_month, 1)->translatedFormat('F Y');
    }
}
