<?php

namespace Database\Seeders;

use App\Models\Faculty;
use App\Models\Program;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * 9 program non-degree.
 *
 * PERHATIAN: nama, kode dan biaya di bawah ini masih CONTOH untuk pengembangan. Daftar program
 * dan tarif yang sebenarnya menunggu data dari KUI (biaya mengacu SK Rektor), karena itu
 * admission_fee & tuition_fee sengaja 0 agar tidak ada angka karangan yang terbawa ke laporan.
 * Semua nominal dalam Rupiah.
 */
class ProgramSeeder extends Seeder
{
    /**
     * @var list<array{code: string, name: string, faculty: string, description: string}>
     */
    private const PROGRAMS = [
        ['code' => 'ND-BIPA', 'name' => 'Indonesian Language for Foreign Speakers (BIPA)', 'faculty' => 'FIB', 'description' => 'Kelas bahasa Indonesia untuk penutur asing.'],
        ['code' => 'ND-BALI-STUDIES', 'name' => 'Balinese Arts and Culture', 'faculty' => 'FIB', 'description' => 'Seni dan budaya Bali untuk mahasiswa asing.'],
        ['code' => 'ND-TOURISM', 'name' => 'Sustainable Tourism', 'faculty' => 'FPAR', 'description' => 'Pariwisata berkelanjutan berbasis studi kasus di Bali.'],
        ['code' => 'ND-MED-ELECTIVE', 'name' => 'Medical Clinical Elective', 'faculty' => 'FK', 'description' => 'Rotasi klinis untuk mahasiswa kedokteran asing.'],
        ['code' => 'ND-TROPICAL-AGRI', 'name' => 'Tropical Agriculture Field Course', 'faculty' => 'FP', 'description' => 'Praktik lapangan pertanian tropis.'],
        ['code' => 'ND-MARINE', 'name' => 'Marine and Coastal Management', 'faculty' => 'FKP', 'description' => 'Pengelolaan wilayah laut dan pesisir.'],
        ['code' => 'ND-ENG-EXCHANGE', 'name' => 'Engineering Student Exchange', 'faculty' => 'FT', 'description' => 'Pertukaran mahasiswa teknik satu semester.'],
        ['code' => 'ND-BUSINESS', 'name' => 'Business and Management Short Course', 'faculty' => 'FEB', 'description' => 'Kursus singkat bisnis dan manajemen.'],
        ['code' => 'ND-SOCIAL', 'name' => 'Social and Political Studies Exchange', 'faculty' => 'FISIP', 'description' => 'Pertukaran mahasiswa ilmu sosial dan politik.'],
    ];

    public function run(): void
    {
        $faculties = Faculty::query()->pluck('id', 'code');

        foreach (self::PROGRAMS as $program) {
            $facultyId = $faculties[$program['faculty']] ?? null;

            if ($facultyId === null) {
                $this->command?->warn("Fakultas {$program['faculty']} belum ada, program {$program['code']} dilewati.");

                continue;
            }

            Program::updateOrCreate(
                ['code' => $program['code']],
                [
                    'faculty_id' => $facultyId,
                    'name' => $program['name'],
                    'slug' => Str::slug($program['name']),
                    'description' => $program['description'],
                    'admission_fee' => 0, // menunggu tarif resmi dari KUI
                    'tuition_fee' => 0,
                    'is_active' => true,
                ],
            );
        }

        $this->command?->info('Program non-degree: '.count(self::PROGRAMS).' baris (data contoh).');
    }
}
