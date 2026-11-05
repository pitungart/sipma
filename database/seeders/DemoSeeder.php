<?php

namespace Database\Seeders;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\Gender;
use App\Enums\Grade;
use App\Enums\LoaStatus;
use App\Enums\MouStatus;
use App\Enums\NumberType;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Enums\StudentCardStatus;
use App\Enums\StudentCourseStatus;
use App\Enums\StudentStatus;
use App\Enums\TeachingRole;
use App\Enums\UserRole;
use App\Enums\VisaProcessStatus;
use App\Models\Agent;
use App\Models\Course;
use App\Models\Faculty;
use App\Models\Lecturer;
use App\Models\PaymentAccount;
use App\Models\Program;
use App\Models\Student;
use App\Models\StudentCard;
use App\Models\TeachingDecree;
use App\Models\User;
use App\Support\Numbering;
use App\Support\PrivateFiles;
use App\Workflow\StudentCardWorkflow;
use App\Workflow\StudentWorkflow;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Data CONTOH untuk mencoba layar & tesis — bukan data KUI. Tidak dipanggil DatabaseSeeder;
 * jalankan manual: `php artisan db:seed --class=DemoSeeder`. Menolak berjalan di production.
 *
 * Akun (password: "password"): agent@sipma.test (MOU disetujui), agent2@sipma.test (MOU menunggu),
 * student@sipma.test (pendaftaran draf), admin.fk@sipma.test (Admin Fakultas Kedokteran).
 * Berkas dummy kecil ditulis ke storage/app/private.
 */
class DemoSeeder extends Seeder
{
    private const COUNTRIES = ['AU', 'JP', 'DE', 'NL', 'IE', 'BR', 'CN', 'GB', 'FR', 'IT', 'KR', 'SE', 'TH', 'MY', 'US'];

    private const FIRST = ['Emma', 'Kenji', 'Lukas', 'Sofie', 'Liam', 'Ana', 'Chen', 'Olivia', 'Noah', 'Mia', 'Yuki', 'Lucas', 'Hana', 'Oscar', 'Ella', 'Arjun', 'Lea', 'Mateo', 'Nina', 'Tomás'];

    private const LAST = ['Wilson', 'Tanaka', 'Becker', 'van Dijk', 'Murphy', 'Souza', 'Wei', 'Brown', 'Schmidt', 'Rossi', 'Sato', 'Martin', 'Kim', 'Berg', 'Novak'];

    private ?string $staffId = null;

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

        $this->staffId = User::query()->where('role', UserRole::SuperAdmin)->value('id');

        $this->demoFees();
        $this->facultyAdmin();

        // Urutan tetap: sebaran status di bawah bergantung pada indeks program
        $programs = Program::query()->where('is_active', true)->orderBy('code')->get();

        $this->coursePackages($programs);
        $this->lecturers($programs);
        $this->teachingDecrees($programs);

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
            // Aktif sebelum LOA terbit agar keduanya punya pendaftar mandiri (indeks kelipatan 5) untuk
            // StudentAccountSeeder. Selesai di tengah agar program Kedokteran (indeks 5) kebagian
            // mahasiswa Aktif dan LOA terbit, sehingga admin.fk@ bisa mencoba mengaktifkan.
            ...array_fill(0, 4, StudentStatus::Enrolled),
            ...array_fill(0, 2, StudentStatus::Completed),
            ...array_fill(0, 5, StudentStatus::LoaIssued),
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
            'address' => 'Level 5, 1 George Street, Sydney NSW 2000',
            'country_code' => $country,
        ]);

        $mou = $agent->mous()->make([
            'file_path' => $this->dummyFile(PrivateFiles::agentDirectory($agent->id, 'mou'), 'pdf'),
            'status' => $mouStatus,
            'mou_number' => $mouStatus === MouStatus::Approved ? Numbering::next(NumberType::Mou) : null,
            'verified_at' => $mouStatus === MouStatus::Approved ? now()->subMonths(6) : null,
        ]);
        // Diunggah sebelum diperiksa
        $mou->created_at = $mouStatus === MouStatus::Approved ? now()->subMonths(6)->subDays(3) : now()->subDays(2);
        $mou->save();

        // MOU disetujui mencakup semua program aktif (agen boleh memilih semuanya)
        if ($mouStatus === MouStatus::Approved) {
            $mou->programs()->sync(Program::query()->where('is_active', true)->pluck('id'));
        }

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
        $enrolledAt = match ($status) {
            StudentStatus::Enrolled => now()->subDays(random_int(30, 90)),
            // Sudah melewati durasi program demo (6 bulan), jadi KTM-nya bisa kedaluwarsa
            StudentStatus::Completed => now()->subMonths(8),
            default => null,
        };

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
            'enrollment_month' => $enrolledAt?->month,
            'enrollment_year' => $enrolledAt?->year,
            'enrolled_at' => $enrolledAt,
            'completed_at' => $status === StudentStatus::Completed ? now()->subDays(random_int(1, 20)) : null,
        ]);
        $student->created_at = $createdAt ?? now();
        $student->registration_number = $isDraft ? null : Numbering::next(NumberType::Application);
        $student->save();

        if (! $isDraft || $user) {
            $this->documents($student, $status);
        }

        if ($status === StudentStatus::Approved || $status->isAccepted()) {
            $this->payments($student, $status);
        }

        if ($status->hasAcademicAccess()) {
            $this->studentCourses($student, $program, $status, $index);
            $this->studentCards($student, $status, $index);
        }

        if ($status->isAccepted()) {
            $student->loa()->create([
                'loa_number' => Numbering::next(NumberType::Loa),
                'file_path' => $this->dummyFile(PrivateFiles::studentDirectory($student->id, 'loa'), 'pdf'),
                'status' => LoaStatus::Uploaded,
                'issued_at' => now()->subDays(random_int(1, 60)),
            ]);

            $this->visa($student, $status, $index);
        }

        return $student;
    }

    private function documents(Student $student, StudentStatus $status): void
    {
        foreach (DocumentType::required() as $type) {
            $documentStatus = match ($status) {
                StudentStatus::Approved, StudentStatus::LoaIssued, StudentStatus::Enrolled, StudentStatus::Completed => DocumentStatus::Approved,
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

    /**
     * Biaya, durasi & rekening CONTOH agar alur pembayaran dan masa berlaku KTM bisa dicoba; data
     * resmi menunggu KUI (ProgramSeeder tetap Rp 0, durasi kosong). Biaya tidak boleh Rp 0, jadi semua
     * program diberi biaya contoh yang sama; tanpa ini pendaftar demo berstatus Disetujui/LOA terbit
     * tidak punya tagihan.
     */
    private function demoFees(): void
    {
        Program::query()->update(['admission_fee' => 500_000, 'tuition_fee' => 4_500_000, 'duration_months' => 6]);

        PaymentAccount::query()->firstOrCreate(
            ['va_number' => '8808 1234 5678 0001'],
            ['bank_name' => 'Bank BPD Bali', 'account_name' => 'Universitas Udayana (DEMO)', 'is_active' => true],
        );
    }

    /**
     * Semua jenis biaya. Disetujui: biaya pendaftaran menunggu verifikasi dan
     * biaya kuliah belum dibayar (bisa dicoba diunggah); LOA terbit: semua terverifikasi.
     */
    private function payments(Student $student, StudentStatus $status): void
    {
        $workflow = app(StudentWorkflow::class);

        foreach ($workflow->requiredPaymentTypes($student) as $type) {
            if ($status === StudentStatus::Approved && $type !== PaymentType::AdmissionFee) {
                continue;
            }

            $verified = $status->isAccepted();
            $account = PaymentAccount::bestFor($type, $student->program_id);

            $student->payments()->create([
                'type' => $type,
                'amount' => $workflow->feeFor($student, $type),
                'payment_account_id' => $account?->id,
                'va_number' => $account?->va_number,
                'proof_file' => $this->dummyFile(PrivateFiles::studentDirectory($student->id, 'payments'), 'png'),
                'status' => $verified ? PaymentStatus::Verified : PaymentStatus::Pending,
                'receipt_number' => $verified ? Numbering::next(NumberType::Receipt) : null,
                'verified_at' => $verified ? now() : null,
            ]);
        }
    }

    /**
     * Admin Fakultas contoh. Kedokteran dipilih karena punya mahasiswa Aktif dan LOA terbit.
     */
    private function facultyAdmin(): void
    {
        User::create([
            'name' => 'Admin Fakultas Kedokteran',
            'email' => 'admin.fk@sipma.test',
            'password' => 'password',
            'role' => UserRole::Admin,
            'faculty_id' => Faculty::query()->where('code', 'FK')->value('id'),
            'email_verified_at' => now(),
            'locale' => 'id',
        ]);
    }

    /**
     * Paket program masuk ke rubrik mahasiswa. Selesai: semua MK dinilai. Aktif bernomor ganjil: baru
     * MK semester 1 yang dinilai agar IP terlihat sambil masih ada MK berjalan.
     */
    private function studentCourses(Student $student, Program $program, StudentStatus $status, int $index): void
    {
        $program->courses()->active()->orderBy('code')->get()->each(function (Course $course, int $position) use ($student, $status, $index): void {
            $graded = $status === StudentStatus::Completed
                || ($index % 2 === 1 && $course->semester === 1);
            // MK tanpa SKS memakai Lulus
            $grade = $graded ? ($course->credits ? [Grade::A, Grade::BPlus, Grade::B][$position % 3] : Grade::Pass) : null;

            $student->studentCourses()->create([
                'course_id' => $course->getKey(),
                'status' => $grade ? StudentCourseStatus::Completed : StudentCourseStatus::Active,
                'grade' => $grade,
                'grade_point' => $grade?->point(),
                'graded_credits' => $grade ? $course->credits : null,
                'graded_at' => $grade ? now() : null,
                'graded_by' => $grade ? $this->staffId : null,
            ]);
        });
    }

    /**
     * Semua keadaan KTM. Aktif bernomor genap sengaja belum punya KTM agar antreannya terlihat.
     */
    private function studentCards(Student $student, StudentStatus $status, int $index): void
    {
        if ($status === StudentStatus::Completed) {
            $index % 2 === 0
                ? $this->studentCard($student, ['status' => StudentCardStatus::Expired])
                : $this->studentCard($student, $this->revoked('Student withdrew the card after completing the program.'));

            return;
        }

        if ($index % 2 === 0) {
            return;
        }

        // Selebihnya: satu hampir habis (memicu pengingat), satu sudah diganti karena hilang (student.active@)
        if ($index % 4 === 3) {
            $this->studentCard($student, ['valid_until' => now()->addDays(20)]);

            return;
        }

        $lost = $this->studentCard($student, $this->revoked('Card reported lost by the student.'));
        $this->studentCard($student, ['issued_at' => now()->subDays(3), 'replaces_id' => $lost->getKey()]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function studentCard(Student $student, array $attributes = []): StudentCard
    {
        return $student->studentCards()->create([
            'card_number' => Numbering::next(NumberType::StudentCard),
            'issued_at' => $student->enrolled_at,
            'valid_until' => StudentCardWorkflow::defaultValidUntil($student),
            'issued_by' => $this->staffId,
            ...$attributes,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function revoked(string $reason): array
    {
        return ['status' => StudentCardStatus::Revoked, 'revoked_at' => now()->subDays(3), 'revoke_reason' => $reason];
    }

    /**
     * Mahasiswa Aktif/Selesai visanya sudah beres; yang baru LOA terbit masih beragam.
     */
    private function visa(Student $student, StudentStatus $status, int $index): void
    {
        $visa = $status->hasAcademicAccess()
            ? VisaProcessStatus::Approved
            : [VisaProcessStatus::NotStarted, VisaProcessStatus::InProcess, VisaProcessStatus::Rejected][$index % 3];

        $student->visaStatus()->create([
            'status' => $visa,
            'notes' => $visa === VisaProcessStatus::Rejected ? 'Immigration asked for an additional sponsor letter.' : null,
            'updated_by' => $this->staffId,
        ]);
    }

    /**
     * Paket contoh untuk demo, bukan kurikulum resmi. Satu MK sengaja tanpa SKS dan satu nonaktif.
     */
    private function coursePackages(Collection $programs): void
    {
        foreach ($programs as $program) {
            foreach ([
                ['DMO101', 'Bahasa Indonesia Dasar', 'Basic Indonesian', 3, 1, true],
                ['DMO102', 'Budaya Bali', 'Balinese Culture', 2, 1, true],
                ['DMO201', 'Proyek Lapangan', 'Field Project', 4, 2, true],
                ['DMO900', 'Orientasi Kampus', 'Campus Orientation', null, null, true],
                ['DMO901', 'Seminar Pilihan', 'Elective Seminar', 2, 2, false],
            ] as [$code, $name, $nameEn, $credits, $semester, $active]) {
                $program->courses()->create([
                    'code' => $code,
                    'name' => $name,
                    'name_en' => $nameEn,
                    'credits' => $credits,
                    'semester' => $semester,
                    'is_active' => $active,
                ]);
            }
        }
    }

    /**
     * Dosen contoh untuk demo, bukan data sungguhan. Satu per fakultas program demo.
     */
    private function lecturers(Collection $programs): void
    {
        $programs->pluck('faculty_id')->unique()->values()->each(fn (string $facultyId, int $i) => Lecturer::create([
            'faculty_id' => $facultyId,
            'name' => 'Dr. Dosen Demo '.($i + 1).', M.Hum.',
            'nip' => 'DEMO-'.str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT),
            'email' => 'dosen'.($i + 1).'@demo.sipma.test',
        ]));
    }

    /**
     * SK contoh untuk demo, per fakultas program demo: yang berjalan dan satu periode tahun lalu.
     */
    private function teachingDecrees(Collection $programs): void
    {
        $programs->groupBy('faculty_id')->values()->each(function (Collection $facultyPrograms, int $i): void {
            $number = str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT);

            foreach ([0, 1] as $yearsAgo) {
                $start = now()->subYears($yearsAgo)->subMonths(3)->startOfMonth();

                $this->teachingDecree(
                    $facultyPrograms,
                    'DEMO/SK/'.$start->year.'/'.$number,
                    $start,
                    $start->copy()->addMonths(5)->endOfMonth(),
                );
            }
        });
    }

    private function teachingDecree(Collection $programs, string $number, CarbonInterface $start, CarbonInterface $end): void
    {
        $facultyId = $programs->first()->faculty_id;

        $decree = TeachingDecree::create([
            'faculty_id' => $facultyId,
            'decree_number' => $number,
            'decree_date' => $start->copy()->subMonth(),
            'period_start' => $start,
            'period_end' => $end,
            'created_by' => $this->staffId,
        ]);

        $decree->update(['document' => $this->dummyFile("teaching-decrees/{$decree->getKey()}", 'pdf')]);

        $lecturer = Lecturer::query()->where('faculty_id', $facultyId)->first();

        Course::query()->whereIn('program_id', $programs->pluck('id'))->active()->get()->each(fn (Course $course) => $decree->assignments()->create([
            'lecturer_id' => $lecturer->getKey(),
            'course_id' => $course->getKey(),
            'role' => TeachingRole::Lecturer,
        ]));
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
