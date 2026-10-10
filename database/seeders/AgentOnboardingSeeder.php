<?php

namespace Database\Seeders;

use App\Enums\MouStatus;
use App\Enums\UserRole;
use App\Models\Agent;
use App\Models\Country;
use App\Models\User;
use App\Support\PrivateFiles;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Akun CONTOH untuk mencoba onboarding agen (UC-12, UC-13) — bukan data KUI. Tidak dipanggil
 * DatabaseSeeder; jalankan manual: `php artisan db:seed --class=AgentOnboardingSeeder`.
 * Aman dijalankan ulang (akun yang sudah ada dilewati) dan menolak berjalan di production.
 *
 * Akun (password: "password"):
 * - agent.new@sipma.test       baru daftar: profil belum lengkap, belum ada MOU
 * - agent.rejected@sipma.test  profil lengkap, MOU ditolak KUI dengan catatan → unggah ulang
 * Tahap "menunggu" & "disetujui" ada di DemoSeeder (agent2@ & agent@sipma.test).
 */
class AgentOnboardingSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->error('AgentOnboardingSeeder tidak boleh dijalankan di production.');

            return;
        }

        // Database kosong: isi master data dulu (negara, Super Admin) seperti DatabaseSeeder
        if (Country::query()->doesntExist()) {
            $this->call(DatabaseSeeder::class);
        }

        // Seperti hasil /register: hanya nama perusahaan, negara, dan email login
        $this->agent('agent.new@sipma.test', 'Pacific Study Partners', [
            'country_code' => 'NZ',
        ]);

        $rejected = $this->agent('agent.rejected@sipma.test', 'Sakura Education Link', [
            'first_name' => 'Haruto',
            'last_name' => 'Sato',
            'phone' => '+81 90 1234 5678',
            'address' => '2-1 Marunouchi, Chiyoda-ku, Tokyo 100-0005',
            'country_code' => 'JP',
        ]);

        if ($rejected?->mous()->doesntExist()) {
            $mou = $rejected->mous()->make([
                'file_path' => $this->dummyPdf(PrivateFiles::agentDirectory($rejected->id, 'mou')),
                'status' => MouStatus::Rejected,
                'revision_note' => 'Halaman tanda tangan belum distempel perusahaan dan halaman 3 tidak ikut terpindai.',
                'verified_by' => User::query()->where('role', UserRole::SuperAdmin)->value('id'),
                'verified_at' => now()->subDay(),
            ]);
            $mou->created_at = now()->subDays(3); // diunggah sebelum diperiksa
            $mou->save();
        }

        $this->command?->info('Akun agen onboarding siap: agent.new@sipma.test, agent.rejected@sipma.test (password: password).');
    }

    /**
     * @param  array<string, string>  $profile
     */
    private function agent(string $email, string $company, array $profile): ?Agent
    {
        if (User::query()->where('email', $email)->exists()) {
            $this->command?->warn("{$email} sudah ada — dilewati.");

            return null;
        }

        $user = User::create([
            'name' => $company,
            'email' => $email,
            'password' => 'password',
            'role' => UserRole::Agent,
            'email_verified_at' => now(),
            'locale' => 'id',
        ]);

        return $user->agent()->create([
            'company_name' => $company,
            'email' => $email,
            ...$profile,
        ]);
    }

    private function dummyPdf(string $directory): string
    {
        $path = $directory.'/'.Str::uuid().'.pdf';

        Storage::disk(PrivateFiles::DISK)->put($path, file_get_contents(resource_path('templates/mou-template.pdf')));

        return $path;
    }
}
