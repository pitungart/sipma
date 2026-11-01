<?php

namespace Tests\Feature\App;

use App\Enums\DocumentType;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Enums\StudentStatus;
use App\Enums\UserRole;
use App\Filament\Admin\Pages\UploadSettings;
use App\Filament\App\Pages\Dashboard;
use App\Filament\App\Pages\EditApplication;
use App\Filament\App\Pages\MyApplication;
use App\Filament\App\Pages\Programs;
use App\Filament\App\Pages\StartApplication;
use App\Filament\Support\ApplicantForm;
use App\Models\Program;
use App\Models\Student;
use App\Models\User;
use App\Notifications\ApplicationSubmitted;
use App\Notifications\RevisionRequested;
use App\Support\FileTemplates;
use App\Support\Rupiah;
use App\Workflow\StudentWorkflow;
use Database\Seeders\DemoSeeder;
use Database\Seeders\StudentAccountSeeder;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\Feature\Workflow\WorkflowTestCase;

/**
 * Panel /app mahasiswa mandiri (§4): satu akun = satu pendaftaran, program yang sedang dibuka
 * tanpa biaya, detail pendaftaran bersama agen.
 */
class StudentPanelTest extends WorkflowTestCase
{
    private Program $open;

    private Program $closed;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('app'));

        $this->open = $this->program(admissionFee: 750_000);
        // Tanpa periode akademik: program "tertutup" = dinonaktifkan KUI
        $this->closed = $this->program();
        $this->closed->update(['is_active' => false]);
    }

    private function account(): User
    {
        $user = $this->studentUser();
        $this->actingAs($user);

        return $user;
    }

    public function test_new_student_starts_from_the_dashboard(): void
    {
        $user = $this->account();

        $this->get(Dashboard::getUrl())
            ->assertOk()
            ->assertSee(__('student.home.stages.start.title'))
            ->assertSee($this->open->name)
            ->assertDontSee($this->closed->name);

        $this->get(MyApplication::getUrl())->assertRedirect(StartApplication::getUrl());

        Livewire::test(StartApplication::class)
            ->assertSet('data.full_name', $user->name)
            ->assertSet('data.email', $user->email);
    }

    public function test_draft_with_minimal_fields_and_open_program_only(): void
    {
        $user = $this->account();

        Livewire::test(StartApplication::class)
            ->fillForm(['passport_number' => 'xa1234567', 'program_id' => $this->closed->id])
            ->call('create')
            ->assertHasFormErrors(['program_id']);

        Livewire::withQueryParams(['program' => $this->open->id])
            ->test(StartApplication::class)
            ->assertSet('data.program_id', $this->open->id)
            ->fillForm(['passport_number' => 'xa1234567'])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertRedirect(MyApplication::getUrl());

        $student = $user->student()->firstOrFail();
        $this->assertSame(StudentStatus::Draft, $student->status);
        $this->assertSame('XA1234567', $student->passport_number);
        $this->assertNull($student->agent_id);
        $this->assertNull($student->enrollment_month, 'filled in on submit');

        // Satu akun = satu pendaftaran
        $this->assertFalse(StartApplication::canAccess());
        $this->get(StartApplication::getUrl())->assertForbidden();
    }

    public function test_student_downloads_templates_but_cannot_manage_them(): void
    {
        FileTemplates::store(DocumentType::Declaration->value, UploadedFile::fake()->create('Surat pernyataan.pdf', 40, 'application/pdf'));
        $user = $this->account();
        $this->student($this->open, owner: $user);

        // Tombol unduh hanya untuk jenis dokumen yang punya template; tautannya juga ada di modal unggah
        Livewire::test(MyApplication::class)
            ->assertSeeHtml(FileTemplates::url(DocumentType::Declaration->value))
            ->assertDontSeeHtml(FileTemplates::url(DocumentType::Passport->value))
            ->mountAction('uploadDocument', ['type' => DocumentType::Declaration->value])
            ->assertSee(__('workflow.documents.template_link'));

        $this->get(FileTemplates::url(DocumentType::Declaration->value))->assertDownload('surat-pernyataan.pdf');
        $this->get(UploadSettings::getUrl(panel: 'admin'))->assertForbidden();
    }

    public function test_student_completes_documents_and_submits(): void
    {
        $admin = $this->superAdmin();
        $user = $this->account();
        $student = $this->student($this->open, owner: $user);

        $page = Livewire::test(MyApplication::class)->assertActionDisabled('submit');

        foreach (DocumentType::required() as $type) {
            $page->callAction('uploadDocument', data: ['file' => $this->file($type)], arguments: ['type' => $type->value])
                ->assertHasNoActionErrors();
        }

        Livewire::test(MyApplication::class)
            ->assertActionEnabled('submit')
            ->callAction('submit', data: ['consent' => true])
            ->assertHasNoActionErrors();

        $this->assertSame(StudentStatus::Submitted, $student->fresh()->status);
        $this->assertSame([now()->month, now()->year], [$student->fresh()->enrollment_month, $student->fresh()->enrollment_year]);
        Notification::assertSentTo($admin, ApplicationSubmitted::class);

        // Setelah diajukan: data terkunci
        Livewire::test(MyApplication::class)->assertActionHidden('edit');
        $this->get(EditApplication::getUrl())->assertForbidden();
    }

    public function test_student_edits_own_draft(): void
    {
        $user = $this->account();
        $student = $this->student($this->open, owner: $user);

        Livewire::test(EditApplication::class)
            ->fillForm(['phone_number' => '+44 7700 900000'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('+44 7700 900000', $student->fresh()->phone_number);
    }

    public function test_edit_wizard_jumps_to_the_step_with_an_error(): void
    {
        $user = $this->account();
        $student = $this->student($this->open, owner: $user);

        // Simpan dari langkah mana pun: nama kosong (langkah 1) → wizard dipindah ke langkah 1
        Livewire::test(EditApplication::class)
            ->fillForm(['full_name' => ''])
            ->call('save')
            ->assertHasFormErrors(['full_name' => 'required']);

        $this->assertNotSame('', $student->fresh()->full_name);
        $this->assertSame(0, ApplicantForm::firstStepWithError(['data.full_name', 'data.program_id']));
        $this->assertSame(2, ApplicantForm::firstStepWithError(['data.passport_number']));
        $this->assertSame(3, ApplicantForm::firstStepWithError(['data.program_id']));
    }

    public function test_dashboard_shows_submission_date_but_not_the_enrollment_period(): void
    {
        // Kicker tanggal hari ini di dasbor tidak boleh ikut berisi "November 2026"
        $this->travelTo('2026-12-15 09:00:00');
        $user = $this->account();
        $student = $this->student($this->open, owner: $user);

        $this->get(Dashboard::getUrl())->assertOk()->assertSee(__('student.home.not_submitted'));

        $student->update(['status' => StudentStatus::Submitted, 'submitted_at' => '2026-11-03 10:00:00']);
        $this->assertSame(11, $student->fresh()->enrollment_month);

        // Periode masuk hanya untuk pelaporan KUI
        $this->get(Dashboard::getUrl())->assertOk()->assertSee('3 Nov 2026')->assertDontSee('November 2026');
        $this->get(MyApplication::getUrl())->assertOk()->assertDontSee(__('admin.applicant.fields.enrollment_period'));
    }

    public function test_catalog_shows_open_programs_without_fees_and_switches_draft_program(): void
    {
        $user = $this->account();
        $other = $this->program(tuitionFee: 2_000_000);
        $student = $this->student($this->open, owner: $user);

        Livewire::test(Programs::class)
            ->assertSee($this->open->name)
            ->assertSee($other->name)
            ->assertDontSee($this->closed->name)
            ->assertDontSee(Rupiah::format(750_000))
            ->assertDontSee(Rupiah::format(2_000_000))
            ->callAction('choose', arguments: ['program' => $other->id]);

        $this->assertSame($other->id, $student->fresh()->program_id);

        // Program yang belum dibuka tidak bisa dipilih walau ID dikirim langsung
        Livewire::test(Programs::class)
            ->call('mountAction', 'choose', ['program' => $this->closed->id])
            ->assertStatus(404);
    }

    public function test_approved_student_pays_and_revision_link_points_to_the_application(): void
    {
        $user = $this->account();
        $student = $this->student($this->open, StudentStatus::Approved, owner: $user);

        Livewire::test(MyApplication::class)
            ->assertSee(Rupiah::format(750_000)) // nominal tetap tampil saat membayar
            ->callAction('uploadPayment', data: ['file' => $this->file()], arguments: ['type' => PaymentType::AdmissionFee->value])
            ->assertHasNoActionErrors();
        $this->assertSame(PaymentStatus::Pending, $student->payments()->sole()->status);

        $notification = new RevisionRequested($student);
        $this->assertSame(MyApplication::getUrl(panel: 'app'), $notification->toDatabase($user)['actions'][0]['url']);
    }

    public function test_students_reach_only_their_own_files_and_other_roles_stay_out(): void
    {
        $this->account();
        $other = $this->student($this->open);
        $foreign = app(StudentWorkflow::class)->uploadDocument($other, DocumentType::Photo, $this->file(DocumentType::Photo));
        $this->student($this->open, owner: auth()->user());

        $this->get(route('files.document', $foreign))->assertForbidden();
        Livewire::test(MyApplication::class)
            ->call('mountAction', 'previewDocument', ['document' => $foreign->getKey()])
            ->assertStatus(404);

        $this->actingAs($this->agent()->user)->get(Dashboard::getUrl())->assertForbidden();
        $this->actingAs($this->superAdmin())->get(MyApplication::getUrl())->assertForbidden();
    }

    public function test_student_account_seeder_links_demo_applicants(): void
    {
        $this->seed(DemoSeeder::class);
        $this->seed(StudentAccountSeeder::class);

        $status = fn (string $email): ?StudentStatus => User::query()->where('email', $email)->first()?->student?->status;

        $this->assertNull($status('student.new@sipma.test'));
        $this->assertSame(StudentStatus::Revision, $status('student.revision@sipma.test'));
        $this->assertSame(StudentStatus::Approved, $status('student.approved@sipma.test'));
        $this->assertSame(StudentStatus::LoaIssued, $status('student.loa@sipma.test'));
        $this->assertSame(5, User::query()->where('role', UserRole::Student)->count());

        // Dijalankan ulang tidak menggandakan akun
        $this->seed(StudentAccountSeeder::class);
        $this->assertSame(5, User::query()->where('role', UserRole::Student)->count());
        $this->assertSame(1, Student::query()->whereHas('user', fn ($q) => $q->where('email', 'student.approved@sipma.test'))->count());
    }
}
