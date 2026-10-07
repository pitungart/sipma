<?php

namespace Tests\Feature\Workflow;

use App\Enums\DocumentType;
use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Enums\UserRole;
use App\Models\Agent;
use App\Models\Country;
use App\Models\Faculty;
use App\Models\Program;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Pembantu bersama tes alur: storage & notifikasi palsu, pendaftar lengkap siap diajukan.
 */
abstract class WorkflowTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Notification::fake();

        Country::create(['code' => 'AU', 'name_en' => 'Australia', 'name_id' => 'Australia']);
    }

    protected function superAdmin(): User
    {
        return User::factory()->create(['role' => UserRole::SuperAdmin]);
    }

    protected function program(int $admissionFee = 0, int $tuitionFee = 0): Program
    {
        $faculty = Faculty::firstOrCreate(['code' => 'FIB'], ['name' => 'Fakultas Ilmu Budaya']);

        return Program::create([
            'faculty_id' => $faculty->id,
            'name' => 'Program '.uniqid(),
            'code' => 'ND-'.strtoupper(uniqid()),
            'admission_fee' => $admissionFee,
            'tuition_fee' => $tuitionFee,
        ]);
    }

    protected function studentUser(): User
    {
        return User::factory()->create(['role' => UserRole::Student]);
    }

    protected function agent(): Agent
    {
        $user = User::factory()->create(['role' => UserRole::Agent]);

        return $user->agent()->create(['company_name' => 'EduBridge', 'email' => $user->email, 'country_code' => 'AU']);
    }

    /**
     * Pendaftar mandiri dengan seluruh field wajib terisi (dokumen belum).
     */
    protected function student(?Program $program = null, StudentStatus $status = StudentStatus::Draft, ?User $owner = null): Student
    {
        return Student::create([
            'user_id' => ($owner ?? $this->studentUser())->id,
            'program_id' => ($program ?? $this->program())->id,
            'full_name' => 'Mona Miha',
            'gender' => Gender::Female,
            'place_of_birth' => 'Sydney',
            'date_of_birth' => '2003-04-05',
            'nationality_code' => 'AU',
            'email' => 'mona@example.test',
            'phone_number' => '+61 412 000 000',
            'permanent_address' => '1 Demo Street',
            'home_university' => 'University of Sydney',
            'home_university_country_code' => 'AU',
            'passport_number' => 'PA1234567',
            'date_of_issued_passport' => '2024-01-01',
            'date_of_passport_expiry' => '2034-01-01',
            'status' => $status,
        ]);
    }

    protected function file(DocumentType $type = DocumentType::Declaration): UploadedFile
    {
        return $type === DocumentType::Photo
            ? UploadedFile::fake()->image('photo.jpg', 300, 400)->size(100)
            : UploadedFile::fake()->create($type->value.'.pdf', 100, 'application/pdf');
    }

    protected function pdf(int $kilobytes = 100): UploadedFile
    {
        return UploadedFile::fake()->create('file.pdf', $kilobytes, 'application/pdf');
    }
}
