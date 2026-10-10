<?php

namespace Database\Seeders;

use App\Enums\StudentStatus;
use App\Enums\UserRole;
use App\Models\AcademicPeriod;
use App\Models\Program;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Akun CONTOH mahasiswa mandiri untuk mencoba panel /app — bukan data KUI. Tidak dipanggil
 * DatabaseSeeder; jalankan setelah DemoSeeder: `php artisan db:seed --class=StudentAccountSeeder`.
 * Aman dijalankan ulang (akun yang ada dilewati) dan menolak berjalan di production.
 *
 * Akun (password: "password"):
 * - student.new@sipma.test       belum punya pendaftaran
 * - student.revision@sipma.test  pendaftar mandiri demo yang diminta revisi
 * - student.approved@sipma.test  disetujui, program BIPA (biaya demo) belum dibayar
 * - student.loa@sipma.test       LOA terbit
 * Draf: student@sipma.test (DemoSeeder).
 */
class StudentAccountSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->error('StudentAccountSeeder tidak boleh dijalankan di production.');

            return;
        }

        if (Student::query()->doesntExist()) {
            $this->call(DemoSeeder::class);
        }

        $this->account('student.new@sipma.test', 'Sophie Laurent');

        $this->link('student.revision@sipma.test', StudentStatus::Revision);
        $this->link('student.approved@sipma.test', StudentStatus::Approved, program: 'ND-BIPA');
        $this->link('student.loa@sipma.test', StudentStatus::LoaIssued);

        $this->command?->info('Akun mahasiswa siap: student.new@, student.revision@, student.approved@, student.loa@sipma.test (password: password).');
    }

    /**
     * Hubungkan akun baru ke pendaftar mandiri demo (tanpa agen & tanpa akun) berstatus $status.
     */
    private function link(string $email, StudentStatus $status, ?string $program = null): void
    {
        if (User::query()->where('email', $email)->exists()) {
            $this->command?->warn("{$email} sudah ada — dilewati.");

            return;
        }

        $student = Student::query()
            ->whereNull('agent_id')
            ->whereNull('user_id')
            ->where('status', $status)
            ->oldest()
            ->first();

        if ($student === null) {
            $this->command?->warn("Tidak ada pendaftar mandiri demo berstatus {$status->value} — {$email} dilewati.");

            return;
        }

        $user = $this->account($email, $student->full_name);
        $student->update(['user_id' => $user->id]);

        // Disetujui + program berbiaya (BIPA di DemoSeeder) agar pembayaran bisa dicoba
        // (program tanpa biaya tidak punya data pembayaran demo, jadi tidak ada yang perlu dibersihkan)
        if ($program !== null && ($target = Program::query()->where('code', $program)->first())) {
            $student->update([
                'program_id' => $target->id,
                'academic_period_id' => AcademicPeriod::currentFor($target->id)?->getKey(),
            ]);
        }
    }

    private function account(string $email, string $name): User
    {
        return User::query()->firstOrCreate(['email' => $email], [
            'name' => $name,
            'password' => 'password',
            'role' => UserRole::Student,
            'email_verified_at' => now(),
            'locale' => 'en',
        ]);
    }
}
