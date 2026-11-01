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
use App\Filament\Admin\Resources\ProgramResource;
use App\Filament\Admin\Resources\StudentResource;
use App\Filament\Admin\Resources\StudentResource\Pages\ListApplicants;
use App\Filament\Admin\Resources\StudentResource\Pages\ViewApplicant;
use App\Models\Faculty;
use App\Models\PaymentAccount;
use App\Models\Student;
use App\Models\User;
use App\Notifications\ApplicationSubmitted;
use App\Support\FileTemplates;
use App\Workflow\MouWorkflow;
use App\Workflow\StudentWorkflow;
use App\Workflow\SubmissionChecklist;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Livewire\DatabaseNotifications;
use Illuminate\Http\UploadedFile;
use Illuminate\Notifications\Channels\DatabaseChannel;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Feature\Workflow\WorkflowTestCase;

class AdmissionsPanelTest extends WorkflowTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function submitted(?User $owner = null): Student
    {
        $student = $this->student($this->program(), owner: $owner);

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

    public function test_detail_page_offers_document_templates_for_download(): void
    {
        FileTemplates::store(DocumentType::GuarantorFinancial->value, UploadedFile::fake()->create('Penjamin.pdf', 40, 'application/pdf'));
        $student = $this->submitted($this->studentUser());

        Livewire::actingAs($this->superAdmin())
            ->test(ViewApplicant::class, ['record' => $student->getRouteKey()])
            ->assertSeeHtml(FileTemplates::url(DocumentType::GuarantorFinancial->value))
            ->assertDontSeeHtml(FileTemplates::url(DocumentType::Transcript->value));
    }

    public function test_detail_page_lists_optional_documents_even_when_empty(): void
    {
        $student = $this->submitted();
        $this->assertFalse($student->documents()->where('type', DocumentType::Transcript)->exists());

        Livewire::actingAs($this->superAdmin())
            ->test(ViewApplicant::class, ['record' => $student->getRouteKey()])
            ->assertSee(DocumentType::Transcript->getLabel())
            ->assertSee(DocumentType::Recommendation->getLabel())
            ->assertSee(__('agent.students.optional'));
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

        $this->paid($student->fresh());
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

    public function test_enrollment_period_is_shown_and_corrected_after_submit(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 3));
        $student = $this->submitted();

        $page = Livewire::actingAs($this->superAdmin())->test(ViewApplicant::class, ['record' => $student->getRouteKey()]);
        $page->assertSee('November 2026')
            ->assertActionVisible('editEnrollment')
            ->assertActionHidden('editApplicant') // data lain tetap terkunci
            ->callAction('editEnrollment', data: ['enrollment_month' => 1, 'enrollment_year' => null])
            ->assertHasActionErrors(['enrollment_year' => 'required_with'])
            ->callAction('editEnrollment', data: ['enrollment_month' => 1, 'enrollment_year' => 2027])
            ->assertHasNoActionErrors();

        $this->assertSame([1, 2027], [$student->fresh()->enrollment_month, $student->fresh()->enrollment_year]);

        // Admin Fakultas hanya membaca
        $own = $this->facultyAdmin($student->program->faculty);
        Livewire::actingAs($own)->test(ViewApplicant::class, ['record' => $student->getRouteKey()])
            ->assertActionHidden('editEnrollment');
    }

    public function test_list_filters_and_sorts_by_enrollment_period(): void
    {
        $november = $this->submitted();
        $november->update(['enrollment_month' => 11, 'enrollment_year' => 2026]);
        $february = $this->submitted();
        $february->update(['enrollment_month' => 2, 'enrollment_year' => 2027]);
        $draft = $this->student();

        Livewire::actingAs($this->superAdmin())
            ->test(ListApplicants::class)
            ->assertTableColumnFormattedStateSet('enrollment_period', 'November 2026', $november)
            ->sortTable('enrollment_period')
            ->assertCanSeeTableRecords([$draft, $november, $february], inOrder: true)
            ->filterTable('enrollment_year', 2027)
            ->assertCanSeeTableRecords([$february])
            ->assertCanNotSeeTableRecords([$november, $draft])
            ->resetTableFilters()
            ->filterTable('enrollment_month', 11)
            ->assertCanSeeTableRecords([$november])
            ->assertCanNotSeeTableRecords([$february, $draft]);
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

    public function test_super_admin_registers_an_applicant_on_their_behalf(): void
    {
        $program = $this->program();
        $admin = $this->superAdmin();

        Livewire::actingAs($admin)
            ->test(StudentResource\Pages\CreateApplicant::class)
            ->fillForm([
                'full_name' => 'Walk-in Applicant',
                'gender' => 'female',
                'nationality_code' => 'AU',
                'place_of_birth' => 'Perth',
                'date_of_birth' => '2002-02-02',
                'email' => 'walkin@example.test',
                'phone_number' => '+61 400 000 000',
                'permanent_address' => '1 Beach Road',
                'home_university' => 'Curtin University',
                'home_university_country_code' => 'AU',
                'passport_number' => 'pb7654321',
                'date_of_issued_passport' => '2024-01-01',
                'date_of_passport_expiry' => '2034-01-01',
                'program_id' => $program->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $student = Student::query()->where('email', 'walkin@example.test')->firstOrFail();
        $this->assertSame(StudentStatus::Draft, $student->status);
        $this->assertSame('PB7654321', $student->passport_number);
        $this->assertNull($student->user_id);

        // Unggah dokumen atas nama pendaftar lalu ajukan dari halaman detail
        $page = Livewire::actingAs($admin)->test(ViewApplicant::class, ['record' => $student->getRouteKey()]);

        $page->callAction('submit')->assertNotified();
        $this->assertSame(StudentStatus::Draft, $student->fresh()->status, 'incomplete application is refused');

        foreach (DocumentType::required() as $type) {
            $page->callAction('uploadDocument', data: ['file' => $this->file($type)], arguments: ['type' => $type->value])
                ->assertHasNoActionErrors();
        }

        $page->callAction('submit');
        $this->assertSame(StudentStatus::Submitted, $student->fresh()->status);
        $this->assertCount(count(DocumentType::required()), $student->documents);

        // Setelah diajukan, data terkunci
        $this->assertFalse(StudentResource::canEdit($student->fresh()));
        $this->actingAs($admin)->get(StudentResource::getUrl('edit', ['record' => $student]))->assertForbidden();
    }

    public function test_admin_draft_needs_only_the_minimal_fields(): void
    {
        $program = $this->program();

        Livewire::actingAs($this->superAdmin())
            ->test(StudentResource\Pages\CreateApplicant::class)
            ->fillForm([
                'full_name' => 'Quick Draft',
                'email' => 'quick@example.test',
                'passport_number' => 'QD1234567',
                'program_id' => $program->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $student = Student::query()->where('email', 'quick@example.test')->firstOrFail();
        $this->assertSame(StudentStatus::Draft, $student->status);
        $this->assertNull($student->date_of_birth);
        $this->assertNull($student->enrollment_month, 'left empty, filled in on submit');
        $this->assertFalse(SubmissionChecklist::for($student)->isComplete(), 'the rest is checked on submit');
    }

    public function test_only_super_admin_sees_the_new_application_button(): void
    {
        $student = $this->submitted();
        $facultyAdmin = $this->facultyAdmin($student->program->faculty);

        $this->actingAs($this->superAdmin())->get('/admin')->assertSee(__('admin.applicant.actions.new'));
        $this->actingAs($this->superAdmin())->get(StudentResource::getUrl('create'))->assertOk();

        $this->actingAs($facultyAdmin)->get('/admin')->assertDontSee(__('admin.applicant.actions.new'));
        $this->actingAs($facultyAdmin)->get(StudentResource::getUrl('create'))->assertForbidden();
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

    public function test_detail_page_payments_tab_matches_the_owner_view(): void
    {
        $locked = $this->submitted();

        Livewire::actingAs($this->superAdmin())
            ->test(ViewApplicant::class, ['record' => $locked->getRouteKey()])
            ->assertSee(__('agent.students.payments.fees_locked_body'))
            ->assertDontSee(__('agent.students.payments.pay_now'));

        $student = $this->student($this->program(admissionFee: 250000), StudentStatus::Approved);
        $payment = app(StudentWorkflow::class)->submitPayment($student, PaymentType::AdmissionFee, $this->pdf());

        Livewire::actingAs($this->superAdmin())
            ->test(ViewApplicant::class, ['record' => $student->getRouteKey()])
            ->assertSee(PaymentType::AdmissionFee->getLabel())
            ->assertSee(__('agent.students.payments.loa_waiting'))
            ->callAction('verifyPayment', arguments: ['payment' => $payment->getKey()]);

        $this->assertSame(PaymentStatus::Verified, $payment->fresh()->status);
    }

    public function test_mou_is_approved_from_the_agency_list(): void
    {
        $program = $this->program();
        $agent = $this->agent();
        app(MouWorkflow::class)->submit($agent, $this->pdf());

        Livewire::actingAs($this->superAdmin())
            ->test(AgentResource\Pages\ListAgents::class)
            ->set('activeTab', MouStatus::Pending->value)
            ->assertCanSeeTableRecords([$agent])
            ->callTableAction('approveMou', $agent, data: ['programs' => []])
            ->assertHasTableActionErrors(['programs' => 'required'])
            ->callTableAction('approveMou', $agent, data: ['programs' => [$program->id]])
            ->assertHasNoTableActionErrors();

        $this->assertTrue($agent->fresh()->hasApprovedMou());
        $this->assertSame([$program->id], $agent->latestMou->programs->pluck('id')->all());
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

    public function test_notification_bell_lists_and_marks_notifications(): void
    {
        $admin = $this->superAdmin();
        $student = $this->submitted();
        // Notification::fake() aktif di test dasar: simpan lewat channel database secara langsung
        $notification = new ApplicationSubmitted($student);
        $notification->id = (string) Str::uuid();
        app(DatabaseChannel::class)->send($admin, $notification);

        Livewire::actingAs($admin)
            ->test(DatabaseNotifications::class)
            ->assertSee(__('admin.shell.notifications.unread', ['count' => 1]))
            ->assertSee($student->full_name)
            ->assertSeeHtml(StudentResource::getUrl('view', ['record' => $student], panel: 'admin'))
            ->call('markNotificationAsRead', $admin->notifications()->value('id'))
            ->assertDontSee(__('admin.shell.notifications.unread', ['count' => 1]))
            ->call('clearNotifications')
            ->assertSee(__('admin.shell.notifications.empty'));

        $this->assertSame(0, $admin->notifications()->count());
    }

    public function test_approve_is_disabled_until_fees_and_va_are_set(): void
    {
        $student = $this->submitted();
        $student->program->update(['admission_fee' => 0]);
        $page = Livewire::actingAs($this->superAdmin())->test(ViewApplicant::class, ['record' => $student->getRouteKey()]);
        $page->callAction('startReview');

        foreach ($student->documents as $document) {
            $page->callAction('approveDocument', arguments: ['document' => $document->getKey()]);
        }

        $message = __('workflow.errors.fees_not_set', ['types' => PaymentType::AdmissionFee->getLabel(), 'program' => $student->program->name]);
        $page->assertActionDisabled('approve')->assertSee($message);

        // Kanban memakai aturan yang sama
        Livewire::test(ListApplicants::class)
            ->call('moveCard', $student->getKey(), StudentStatus::Approved->value)
            ->assertNotified($message);
        $this->assertSame(StudentStatus::InReview, $student->fresh()->status);

        $student->program->update(['admission_fee' => 250_000]);
        PaymentAccount::query()->delete();
        Livewire::test(ViewApplicant::class, ['record' => $student->getRouteKey()])
            ->assertActionDisabled('approve')
            ->assertSee(__('workflow.errors.va_not_set', ['types' => PaymentType::AdmissionFee->getLabel().', '.PaymentType::TuitionFee->getLabel()]));
    }

    public function test_program_fees_must_be_above_zero(): void
    {
        Livewire::actingAs($this->superAdmin())
            ->test(ProgramResource\Pages\ManagePrograms::class)
            ->callAction(CreateAction::class, data: [
                'faculty_id' => Faculty::create(['name' => 'Fakultas Teknik', 'code' => 'FT'])->id,
                'name' => 'Gratis',
                'code' => 'ND-FREE',
                'admission_fee' => 0,
                'tuition_fee' => 0,
            ])
            ->assertHasActionErrors(['admission_fee' => 'min', 'tuition_fee' => 'min']);

        $this->assertDatabaseMissing('programs', ['code' => 'ND-FREE']);
    }
}
