<?php

namespace Database\Seeders;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\Gender;
use App\Enums\LoaStatus;
use App\Enums\MouStatus;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Enums\StudentStatus;
use App\Enums\UserRole;
use App\Models\AcademicPeriod;
use App\Models\Agent;
use App\Models\Program;
use App\Models\Student;
use App\Models\User;
use App\Support\PrivateFiles;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Data CONTOH untuk mencoba layar & tesis — bukan data KUI. Tidak dipanggil DatabaseSeeder;
 * jalankan manual: `php artisan db:seed --class=DemoSeeder`. Menolak berjalan di production.
 *
 * Akun (password: "password"): agent@sipma.test (MOU disetujui), agent2@sipma.test (MOU menunggu),
 * student@sipma.test (pendaftaran draf). Berkas dummy kecil ditulis ke storage/app/private.
 */
class DemoSeeder extends Seeder
{
    private const COUNTRIES = ['AU', 'JP', 'DE', 'NL', 'IE', 'BR', 'CN', 'GB', 'FR', 'IT', 'KR', 'SE', 'TH', 'MY', 'US'];

    private const FIRST = ['Emma', 'Kenji', 'Lukas', 'Sofie', 'Liam', 'Ana', 'Chen', 'Olivia', 'Noah', 'Mia', 'Yuki', 'Lucas', 'Hana', 'Oscar', 'Ella', 'Arjun', 'Lea', 'Mateo', 'Nina', 'Tomás'];

    private const LAST = ['Wilson', 'Tanaka', 'Becker', 'van Dijk', 'Murphy', 'Souza', 'Wei', 'Brown', 'Schmidt', 'Rossi', 'Sato', 'Martin', 'Kim', 'Berg', 'Novak'];

    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->error('DemoSeeder tidak boleh dijalankan di production.');

            return;
        }

        if (User::query()->where('email', 'agent@sipma.test')->exists()) {
            $this->command?->warn('Data demo sudah ada — dilewati. Jalankan migrate:fresh --seed lalu seeder ini untuk mengulang.');

            return;
        }

        $this->call(DatabaseSeeder::class);

        $programs = Program::query()->where('is_active', true)->get();
        $this->periods($programs);

        $agent = $this->agent('agent@sipma.test', 'EduBridge Australia', 'AU', MouStatus::Approved);
        $this->agent('agent2@sipma.test', 'Nusantara Study Link', 'MY', MouStatus::Pending);

        $self = User::create([
            'name' => 'Emma Wilson',
            'email' => 'student@sipma.test',
            'password' => 'password',
            'role' => UserRole::Student,
            'email_verified_at' => now(),
        ]);

        $this->student($programs->first(), StudentStatus::Draft, user: $self, name: 'Emma Wilson', createdAt: now());

        // Sebaran status yang wajar: banyak di tengah alur, sedikit draf.
        $statuses = [
            ...array_fill(0, 6, StudentStatus::Submitted),
            ...array_fill(0, 6, StudentStatus::InReview),
            ...array_fill(0, 5, StudentStatus::Revision),
            ...array_fill(0, 5, StudentStatus::Approved),
            ...array_fill(0, 7, StudentStatus::LoaIssued),
            ...array_fill(0, 3, StudentStatus::Draft),
        ];

        foreach ($statuses as $i => $status) {
            $this->student(
                $programs[$i % $programs->count()],
                $status,
                agent: $i % 5 === 0 ? null : $agent, // ±80% lewat agen (data KUI)
                name: self::FIRST[$i % count(self::FIRST)].' '.self::LAST[($i * 7) % count(self::LAST)],
                createdAt: CarbonImmutable::now()->subDays(random_int(0, 420)),
                index: $i,
            );
        }

        $this->command?->info('Data demo dibuat: 2 agen, '.Student::query()->count().' pendaftar. Password semua akun: "password".');
    }

    private function periods($programs): void
    {
        foreach ($programs->take(3) as $i => $program) {
            AcademicPeriod::create([
                'program_id' => $program->id,
                'name' => ['BIPA Februari 2027', 'Summer Course 2027', 'Exchange Spring 2027'][$i],
                'code' => ['BIPA-FEB-27', 'SUMMER-27', 'EXCH-SPR-27'][$i],
                'registration_opens_at' => now()->addDays([-10, 5, 20][$i]),
                'registration_closes_at' => now()->addDays([25, 40, 60][$i]),
                'starts_on' => now()->addDays([70, 90, 120][$i]),
                'ends_on' => now()->addDays([160, 120, 240][$i]),
                'quota' => [30, null, 15][$i],
            ]);
        }
    }

    private function agent(string $email, string $company, string $country, MouStatus $mouStatus): Agent
    {
        $user = User::create([
            'name' => $company,
            'email' => $email,
            'password' => 'password',
            'role' => UserRole::Agent,
            'email_verified_at' => now(),
        ]);

        $agent = $user->agent()->create([
            'company_name' => $company,
            'first_name' => 'Kate',
            'last_name' => 'Tan',
            'email' => $email,
            'phone' => '+61 412 000 000',
            'country_code' => $country,
        ]);

        $agent->mous()->create([
            'file_path' => $this->dummyFile(PrivateFiles::agentDirectory($agent->id, 'mou'), 'pdf'),
            'status' => $mouStatus,
            'verified_at' => $mouStatus === MouStatus::Approved ? now()->subMonths(6) : null,
        ]);

        return $agent;
    }

    private function student(
        Program $program,
        StudentStatus $status,
        ?Agent $agent = null,
        ?User $user = null,
        string $name = 'Demo Student',
        ?\DateTimeInterface $createdAt = null,
        int $index = 0,
    ): Student {
        $country = self::COUNTRIES[$index % count(self::COUNTRIES)];
        $isDraft = $status === StudentStatus::Draft;

        $student = new Student([
            'user_id' => $user?->id,
            'agent_id' => $agent?->id,
            'program_id' => $program->id,
            'full_name' => $name,
            'gender' => [Gender::Female, Gender::Male][$index % 2],
            'place_of_birth' => 'City '.($index + 1),
            'date_of_birth' => now()->subYears(20 + $index % 6)->subDays($index * 11),
            'nationality_code' => $country,
            'email' => Str::slug($name).'.'.$index.'@demo.sipma.test',
            'phone_number' => '+'.(60 + $index).' 812 000 '.str_pad((string) $index, 3, '0', STR_PAD_LEFT),
            'permanent_address' => ($index + 1).' Demo Street',
            'home_university' => 'Demo University '.$country,
            'home_university_country_code' => $country,
            'passport_number' => 'PA'.str_pad((string) (100000 + $index * 37), 7, '0', STR_PAD_LEFT),
            'date_of_issued_passport' => now()->subYears(2),
            'date_of_passport_expiry' => now()->addYears(5),
            'status' => $status,
            'submitted_at' => $isDraft ? null : $createdAt,
            'revision_note' => $status === StudentStatus::Revision ? 'Please re-upload the medical statement signed by a doctor.' : null,
        ]);
        $student->created_at = $createdAt ?? now();
        $student->save();

        if (! $isDraft || $user) {
            $this->documents($student, $status);
        }

        if (in_array($status, [StudentStatus::Approved, StudentStatus::LoaIssued], true)) {
            $this->payment($student, $status === StudentStatus::LoaIssued ? PaymentStatus::Verified : PaymentStatus::Pending);
        }

        if ($status === StudentStatus::LoaIssued) {
            $student->loa()->create([
                'loa_number' => 'DEMO/LOA/'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
                'file_path' => $this->dummyFile(PrivateFiles::studentDirectory($student->id, 'loa'), 'pdf'),
                'status' => LoaStatus::Uploaded,
                'issued_at' => now()->subDays(random_int(1, 60)),
            ]);
        }

        return $student;
    }

    private function documents(Student $student, StudentStatus $status): void
    {
        foreach (DocumentType::required() as $type) {
            $documentStatus = match ($status) {
                StudentStatus::Approved, StudentStatus::LoaIssued => DocumentStatus::Approved,
                StudentStatus::Revision => $type === DocumentType::MedicalStatement ? DocumentStatus::Revision : DocumentStatus::Approved,
                default => DocumentStatus::Pending,
            };

            $extension = $type === DocumentType::Photo ? 'png' : 'pdf';

            $student->documents()->create([
                'type' => $type,
                'file_path' => $this->dummyFile(PrivateFiles::studentDirectory($student->id, 'documents'), $extension),
                'original_name' => $type->value.'.'.$extension,
                'file_size' => 1024,
                'mime_type' => $extension === 'png' ? 'image/png' : 'application/pdf',
                'status' => $documentStatus,
                'revision_note' => $documentStatus === DocumentStatus::Revision ? 'The signature of the doctor is missing.' : null,
                'reviewed_at' => $documentStatus === DocumentStatus::Pending ? null : now(),
            ]);
        }
    }

    private function payment(Student $student, PaymentStatus $status): void
    {
        $student->payments()->create([
            'type' => PaymentType::AdmissionFee,
            'amount' => $student->program->admission_fee,
            'proof_file' => $this->dummyFile(PrivateFiles::studentDirectory($student->id, 'payments'), 'png'),
            'status' => $status,
            'verified_at' => $status === PaymentStatus::Verified ? now() : null,
        ]);
    }

    /**
     * Berkas dummy kecil yang valid (PDF satu halaman / PNG 1×1) agar pratinjau bisa dicoba.
     */
    private function dummyFile(string $directory, string $extension): string
    {
        $path = $directory.'/'.Str::uuid().'.'.$extension;

        Storage::disk(PrivateFiles::DISK)->put($path, $extension === 'png'
            ? base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==')
            : "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj 2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj 3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 595 842]>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n");

        return $path;
    }
}
