<?php

namespace Tests\Feature\Admin;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\MouStatus;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Enums\StudentStatus;
use App\Enums\UserRole;
use App\Filament\Admin\Resources\AgentResource;
use App\Filament\Admin\Resources\PaymentResource;
use App\Filament\Admin\Resources\StudentResource;
use App\Filament\Admin\Resources\StudentResource\Pages\ListApplicants;
use App\Filament\Admin\Resources\StudentResource\Pages\ViewApplicant;
use App\Models\Faculty;
use App\Models\Student;
use App\Models\User;
use App\Notifications\ApplicationSubmitted;
use App\Workflow\MouWorkflow;
use App\Workflow\StudentWorkflow;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\Feature\Workflow\WorkflowTestCase;

class AdmissionsPanelTest extends WorkflowTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function submitted(?User $owner = null, int $admissionFee = 0): Student
    {
        $student = $this->student($this->program(admissionFee: $admissionFee), owner: $owner);

        foreach (DocumentType::required() as $type) {
            app(StudentWorkflow::class)->uploadDocument($student, $type, $this->file($type));
        }

        return app(StudentWorkflow::class)->submit($student);
    }

    private function facultyAdmin(Faculty $faculty): User
    {
        return User::factory()->create(['role' => UserRole::Admin, 'faculty_id' => $faculty->id]);
    }

    public function test_menus_follow_the_role(): void
    {
        $this->actingAs($this->superAdmin());
        $this->assertTrue(StudentResource::canViewAny());
        $this->assertTrue(PaymentResource::canViewAny());
        $this->assertTrue(AgentResource::canViewAny());

        $this->actingAs($this->facultyAdmin(Faculty::create(['name' => 'Fakultas Teknik', 'code' => 'FT'])));
        $this->assertTrue(StudentResource::canViewAny());
        $this->assertFalse(PaymentResource::canViewAny());
        $this->assertFalse(AgentResource::canViewAny());

        $this->get('/admin/payments')->assertForbidden();
        $this->get('/admin/agents')->assertForbidden();
    }

    public function test_status_tabs_filter_the_list(): void
    {
        $submitted = $this->submitted();
        $draft = $this->student();

        Livewire::actingAs($this->superAdmin())
            ->test(ListApplicants::class)
            ->assertCanSeeTableRecords([$submitted, $draft])
            ->set('activeTab', StudentStatus::Submitted->value)
            ->assertCanSeeTableRecords([$submitted])
            ->assertCanNotSeeTableRecords([$draft]);

        // Mode kanban tetap merender wadah modal aksi (modal revisi saat kartu diseret)
        $this->actingAs($this->superAdmin())
            ->get(StudentResource::getUrl('index', ['view' => 'kanban']))
            ->assertOk()
            ->assertSee('fi-modal', false);

        $this->actingAs($this->superAdmin())
            ->get(StudentResource::getUrl('index', ['activeTab' => 'submitted']))
            ->assertOk()
            ->assertSee('sipma-master-tabs', false);
    }

    public function test_full_review_from_the_detail_page(): void
    {
        $owner = $this->studentUser();
        $student = $this->submitted($owner);
        $page = Livewire::actingAs($this->superAdmin())->test(ViewApplicant::class, ['record' => $student->getRouteKey()]);

        $page->assertActionVisible('startReview')
            ->assertActionHidden('approve')
            ->callAction('startReview');
        $this->assertSame(StudentStatus::InReview, $student->fresh()->status);

        $page->assertActionDisabled('approve');

        foreach ($student->documents as $document) {
            $page->callAction('approveDocument', arguments: ['document' => $document->getKey()]);
        }
        $this->assertSame(0, $student->documents()->where('status', '!=', DocumentStatus::Approved)->count());

        $page->assertActionEnabled('approve')->callAction('approve');
        $this->assertSame(StudentStatus::Approved, $student->fresh()->status);

        $page->callAction('issueLoa', data: [
            'file' => UploadedFile::fake()->create('loa.pdf', 200, 'application/pdf'),
            'loa_number' => 'LOA/KUI/001',
        ])->assertHasNoActionErrors();

        $this->assertSame(StudentStatus::LoaIssued, $student->fresh()->status);
        $this->assertSame('LOA/KUI/001', $student->loa->loa_number);
    }

    public function test_revision_from_the_detail_page_needs_a_note_per_document(): void
    {
        $student = $this->submitted();
        app(StudentWorkflow::class)->startReview($student);
        $document = $student->documents()->first();

        Livewire::actingAs($this->superAdmin())
            ->test(ViewApplicant::class, ['record' => $student->getRouteKey()])
            ->callAction('reviseDocument', data: ['note' => ''], arguments: ['document' => $document->getKey()])
            ->assertHasActionErrors(['note' => 'required'])
            ->callAction('reviseDocument', data: ['note' => 'Blurry scan'], arguments: ['document' => $document->getKey()])
            ->callAction('requestRevision');

        $this->assertSame(DocumentStatus::Revision, $document->fresh()->status);
        $this->assertSame(StudentStatus::Revision, $student->fresh()->status);
    }

    public function test_faculty_admin_sees_a_read_only_summary_of_own_faculty(): void
    {
        $student = $this->submitted();
        $own = $this->facultyAdmin($student->program->faculty);

        $this->actingAs($own)
            ->get(StudentResource::getUrl('view', ['record' => $student]))
            ->assertOk()
            ->assertSee($student->passport_number)
            ->assertDontSee("tab === 'documents'", false); // tab Dokumen tidak dirender

        $page = Livewire::actingAs($own)->test(ViewApplicant::class, ['record' => $student->getRouteKey()]);
        $page->assertActionHidden('startReview');

        // Tombol yang tidak dirender tetap tidak bisa dipanggil lewat Livewire
        $page->call('mountAction', 'approveDocument', ['document' => $student->documents()->first()->getKey()])
            ->assertForbidden();

        $this->assertSame(DocumentStatus::Pending, $student->documents()->first()->status);
    }

    public function test_faculty_admin_cannot_open_another_faculty_applicant(): void
    {
        $student = $this->submitted();
        $other = $this->facultyAdmin(Faculty::create(['name' => 'Fakultas Teknik', 'code' => 'FT']));

        $this->actingAs($other)->get(StudentResource::getUrl('view', ['record' => $student]))->assertNotFound();
    }

    public function test_kanban_moves_only_along_legal_transitions(): void
    {
        $student = $this->submitted();
        $page = Livewire::actingAs($this->superAdmin())
            ->test(ListApplicants::class)
            ->set('display', 'kanban')
            ->assertSee(__('admin.applicant.kanban.start_review'));

        // Diajukan → Disetujui melompati verifikasi: ditolak
        $page->call('moveCard', $student->getKey(), StudentStatus::Approved->value)->assertNotified();
        $this->assertSame(StudentStatus::Submitted, $student->fresh()->status);

        $page->call('moveCard', $student->getKey(), StudentStatus::InReview->value);
        $this->assertSame(StudentStatus::InReview, $student->fresh()->status);

        // Ke "Perlu revisi" membuka modal catatan, lalu mengirim revisi
        $page->call('moveCard', $student->getKey(), StudentStatus::Revision->value)
            ->assertActionMounted('boardRevision')
            ->setActionData(['note' => 'Please re-upload the passport scan.'])
            ->callMountedAction();

        $this->assertSame(StudentStatus::Revision, $student->fresh()->status);
        $this->assertSame('Please re-upload the passport scan.', $student->fresh()->revision_note);
    }

    public function test_kanban_approve_needs_approved_documents(): void
    {
        $student = $this->submitted();
        app(StudentWorkflow::class)->startReview($student);

        Livewire::actingAs($this->superAdmin())
            ->test(ListApplicants::class, ['display' => 'kanban'])
            ->call('moveCard', $student->getKey(), StudentStatus::Approved->value)
            ->assertNotified();

        $this->assertSame(StudentStatus::InReview, $student->fresh()->status);
    }

    public function test_faculty_admin_sees_the_board_but_cannot_move_cards(): void
    {
        $student = $this->submitted();
        $admin = $this->facultyAdmin($student->program->faculty);

        $this->actingAs($admin)
            ->get(StudentResource::getUrl('index', ['view' => 'kanban']))
            ->assertOk()
            ->assertSee($student->full_name)
            ->assertDontSee('draggable="true"', false);

        Livewire::actingAs($admin)
            ->test(ListApplicants::class, ['display' => 'kanban'])
            ->call('moveCard', $student->getKey(), StudentStatus::InReview->value)
            ->assertForbidden();

        $this->assertSame(StudentStatus::Submitted, $student->fresh()->status);
    }

    public function test_display_toggle_switches_between_table_and_kanban(): void
    {
        $this->submitted();

        Livewire::actingAs($this->superAdmin())
            ->test(ListApplicants::class)
            ->assertSeeHtml('sipma-segment-lg')
            ->assertActionVisible('export')
            ->set('display', 'kanban')
            ->assertSeeHtml('sipma-board-columns')
            ->assertActionHidden('export')
            ->set('display', 'table')
            ->assertDontSeeHtml('sipma-board-columns');
    }

    public function test_payments_are_verified_and_rejected_from_the_list(): void
    {
        $student = $this->student($this->program(admissionFee: 250000), StudentStatus::Approved);
        $payment = app(StudentWorkflow::class)->submitPayment($student, PaymentType::AdmissionFee, $this->pdf());

        Livewire::actingAs($this->superAdmin())
            ->test(PaymentResource\Pages\ListPayments::class)
            ->assertSet('activeTab', 'pending')
            ->assertCanSeeTableRecords([$payment])
            ->callTableAction('reject', $payment, data: ['note' => 'Wrong amount']);

        $this->assertSame(PaymentStatus::Rejected, $payment->fresh()->status);

        $again = app(StudentWorkflow::class)->submitPayment($student->fresh(), PaymentType::AdmissionFee, $this->pdf());

        Livewire::actingAs($this->superAdmin())
            ->test(PaymentResource\Pages\ListPayments::class)
            ->callTableAction('verify', $again);

        $this->assertSame(PaymentStatus::Verified, $again->fresh()->status);
    }

    public function test_mou_is_approved_from_the_agency_list(): void
    {
        $agent = $this->agent();
        app(MouWorkflow::class)->submit($agent, $this->pdf());

        Livewire::actingAs($this->superAdmin())
            ->test(AgentResource\Pages\ListAgents::class)
            ->set('activeTab', MouStatus::Pending->value)
            ->assertCanSeeTableRecords([$agent])
            ->callTableAction('approveMou', $agent);

        $this->assertTrue($agent->fresh()->hasApprovedMou());
    }

    public function test_export_follows_the_active_tab(): void
    {
        $this->submitted();
        $this->student();

        Livewire::actingAs($this->superAdmin())
            ->test(ListApplicants::class)
            ->set('activeTab', StudentStatus::Submitted->value)
            ->callAction('export')
            ->assertFileDownloaded('sipma-pendaftar-'.now()->format('Ymd').'.csv');
    }

    public function test_kui_notification_opens_the_applicant_page(): void
    {
        $admin = $this->superAdmin();
        $student = $this->submitted();

        $notification = new ApplicationSubmitted($student);

        $this->assertSame(
            StudentResource::getUrl('view', ['record' => $student], panel: 'admin'),
            $notification->toMail($admin)->actionUrl,
        );
    }
}
