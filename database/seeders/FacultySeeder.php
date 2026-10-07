<?php

namespace Database\Seeders;

use App\Models\Faculty;
use Illuminate\Database\Seeder;

/**
 * Fakultas di Universitas Udayana. Kode dipakai sebagai kunci pencocokan, jadi aman diulang.
 *
 * Catatan: daftar ini perlu dikonfirmasi ke KUI sebelum dipakai di lingkungan produksi.
 */
class FacultySeeder extends Seeder
{
    /**
     * @var array<string, string>
     */
    private const FACULTIES = [
        'FIB' => 'Fakultas Ilmu Budaya',
        'FK' => 'Fakultas Kedokteran',
        'FH' => 'Fakultas Hukum',
        'FT' => 'Fakultas Teknik',
        'FP' => 'Fakultas Pertanian',
        'FEB' => 'Fakultas Ekonomi dan Bisnis',
        'FAPET' => 'Fakultas Peternakan',
        'FKH' => 'Fakultas Kedokteran Hewan',
        'FMIPA' => 'Fakultas Matematika dan Ilmu Pengetahuan Alam',
        'FTP' => 'Fakultas Teknologi Pertanian',
        'FPAR' => 'Fakultas Pariwisata',
        'FISIP' => 'Fakultas Ilmu Sosial dan Ilmu Politik',
        'FKP' => 'Fakultas Kelautan dan Perikanan',
    ];

    public function run(): void
    {
        foreach (self::FACULTIES as $code => $name) {
            Faculty::updateOrCreate(['code' => $code], ['name' => $name]);
        }

        $this->command?->info('Master fakultas: '.count(self::FACULTIES).' baris.');
    }
}
