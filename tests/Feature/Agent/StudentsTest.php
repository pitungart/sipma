<?php

namespace Tests\Feature\Agent;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Enums\StudentStatus;
use App\Filament\Agent\Resources\StudentResource;
use App\Filament\Agent\Resources\StudentResource\Pages\CreateStudent;
use App\Filament\Agent\Resources\StudentResource\Pages\EditStudent;
use App\Filament\Agent\Resources\StudentResource\Pages\ListStudents;
use App\Filament\Agent\Resources\StudentResource\Pages\ViewStudent;
use App\Models\AcademicPeriod;
use App\Models\Agent;
use App\Models\PaymentAccount;
use App\Models\Program;
use App\Models\Student;
use App\Notifications\ApplicationSubmitted;
use App\Notifications\PaymentSubmitted;
use App\Notifications\RevisionRequested;
use App\Support\Rupiah;
use App\Workflow\MouWorkflow;
use App\Workflow\StudentWorkflow;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\Feature\Workflow\WorkflowTestCase;

/**
 * "Mahasiswa saya" agen (UC-14, UC-07, UC-08, UC-11, UC-01, UC-03).
 */
class StudentsTest extends WorkflowTestCase
{
    private Program $covered;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('agent'));
        $this->covered = $this->program();
    }

    /**
     * Agen dengan MOU disetujui yang hanya mencakup $this->covered.
     */
    private function partner(): Agent
    {
        $agent = $this->agent();
        $mou = app(MouWorkflow::class)->submit($agent, $this->pdf());
        $this->actingAs($this->superAdmin());
        app(MouWorkflow::class)->approve($mou, [$this->covered->id]);
        $this->actingAs($agent->user);

        return $agent->fresh();
    }

    private function studentOf(Agent $agent, StudentStatus $status = StudentStatus::Draft): Student
    {
        $student = $this->student($this->covered, $status);
        $student->update(['user_id' => null, 'agent_id' => $agent->id]);

        return $student->fresh();
    }

    public function test_menu_is_locked_until_the_mou_is_approved(): void
    {
        $agent = $this->agent();
        $this->actingAs($agent->user);

        $this->assertFalse(StudentResource::canAccess());
        $this->get(StudentResource::getUrl())->assertForbidden();

        $this->partner();
        $this->assertTrue(StudentResource::canAccess());
        $this->get(StudentResource::getUrl())->assertOk();
    }

    public function test_list_shows_only_the_agents_own_students(): void
    {
        $mine = $this->studentOf($this->partner());
        $theirs = $this->studentOf($this->agent());
        $self = $this->student($this->covered);

        Livewire::test(ListStudents::class)
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$theirs, $self]);

        $this->get(StudentResource::getUrl('view', ['record' => $theirs]))->assertNotFound();
    }

    public function test_draft_needs_only_name_email_passport_and_program(): void
    {
        $agent = $this->partner();
        $period = AcademicPeriod::create([
            'program_id' => $this->covered->id,
            'name' => 'Batch Oktober',
            'code' => 'OKT-26',
            'registration_opens_at' => now()->subWeek(),
            'registration_closes_at' => now()->addMonth(),
            'is_active' => true,
        ]);

        Livewire::test(CreateStudent::class)
            // Program satu-satunya di MOU terisi otomatis
            ->assertFormSet(['program_id' => $this->covered->id])
            ->fillForm([
                'full_name' => 'Kenji Tanaka',
                'email' => 'kenji@example.test',
                'passport_number' => 'tk1234567',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $student = Student::query()->where('email', 'kenji@example.test')->firstOrFail();
        $this->assertSame($agent->id, $student->agent_id);
        $this->assertNull($student->user_id);
        $this->assertSame(StudentStatus::Draft, $student->status);
        $this->assertSame('TK1234567', $student->passport_number);
        $this->assertSame($period->id, $student->academic_period_id, 'open period is picked automatically');
    }

    public function test_program_outside_the_mou_is_rejected(): void
    {
        $this->partner();
        $other = $this->program();

        Livewire::test(CreateStudent::class)
            ->fillForm([
                'full_name' => 'Kenji Tanaka',
                'email' => 'kenji@example.test',
                'passport_number' => 'TK1234567',
                'program_id' => $other->id,
            ])
            ->call('create')
            ->assertHasFormErrors(['program_id']);

        Livewire::test(CreateStudent::class)
            ->fillForm(['full_name' => '', 'email' => '', 'passport_number' => ''])
            ->call('create')
            ->assertHasFormErrors(['full_name' => 'required', 'email' => 'required', 'passport_number' => 'required']);
    }

    public function test_agent_uploads_documents_and_submits(): void
    {
        $admin = $this->superAdmin();
        $agent = $this->partner();
        $student = $this->studentOf($agent);

        $page = Livewire::test(ViewStudent::class, ['record' => $student->getRouteKey()])
            ->assertSee(__('agent.students.checklist_title'))
            ->assertActionDisabled('submit');

        foreach (DocumentType::required() as $type) {
            $page->callAction('uploadDocument', data: ['file' => $this->file($type)], arguments: ['type' => $type->value])
                ->assertHasNoActionErrors();
        }

        $page = Livewire::test(ViewStudent::class, ['record' => $student->getRouteKey()])
            ->assertActionEnabled('submit')
            ->callAction('submit', data: ['consent' => false])
            ->assertHasActionErrors(['consent' => 'accepted']);
        $this->assertSame(StudentStatus::Draft, $student->fresh()->status);

        $page->callAction('submit', data: ['consent' => true])->assertHasNoActionErrors();

        $this->assertSame(StudentStatus::Submitted, $student->fresh()->status);
        Notification::assertSentTo($admin, ApplicationSubmitted::class);

        // Setelah diajukan: terkunci
        Livewire::test(ViewStudent::class, ['record' => $student->getRouteKey()])
            ->assertActionHidden('submit')
            ->assertActionHidden('edit')
            ->assertActionHidden('delete');
        $this->get(StudentResource::getUrl('edit', ['record' => $student]))->assertForbidden();
    }

    public function test_revision_shows_kui_notes_and_only_unapproved_documents_can_be_replaced(): void
    {
        $agent = $this->partner();
        $student = $this->studentOf($agent);
        $workflow = app(StudentWorkflow::class);

        foreach (DocumentType::required() as $type) {
            $workflow->uploadDocument($student, $type, $this->file($type));
        }
        $workflow->submit($student);

        $this->actingAs($this->superAdmin());
        $workflow->startReview($student->fresh());
        $photo = $student->documents()->where('type', DocumentType::Photo)->first();
        $passport = $student->documents()->where('type', DocumentType::Passport)->first();
        $workflow->reviewDocument($photo, DocumentStatus::Revision, 'Background must be red');
        $workflow->reviewDocument($passport, DocumentStatus::Approved);
        $workflow->requestRevision($student->fresh(), 'Please fix the photo.');

        $this->actingAs($agent->user);
        Notification::assertSentTo($agent->user, RevisionRequested::class, fn (RevisionRequested $n): bool => $n->toDatabase($agent->user)['actions'][0]['url'] === StudentResource::getUrl('view', ['record' => $student], panel: 'agent'));

        $page = Livewire::test(ViewStudent::class, ['record' => $student->getRouteKey()])
            ->assertSee('Please fix the photo.')
            ->assertSee('Background must be red');

        // Dokumen yang sudah disetujui KUI tidak bisa diganti, walau dipanggil langsung
        $page->callAction('uploadDocument', data: ['file' => $this->file(DocumentType::Passport)], arguments: ['type' => DocumentType::Passport->value])
            ->assertNotified(__('workflow.errors.document_locked', ['document' => DocumentType::Passport->getLabel()]));

        $page->callAction('uploadDocument', data: ['file' => $this->file(DocumentType::Photo)], arguments: ['type' => DocumentType::Photo->value])
            ->assertHasNoActionErrors();
        $this->assertSame(DocumentStatus::Pending, $photo->fresh()->status);

        $page->callAction('submit', data: ['consent' => true]);
        $this->assertSame(StudentStatus::Submitted, $student->fresh()->status);
    }

    public function test_agent_edits_and_deletes_only_drafts(): void
    {
        $agent = $this->partner();
        $draft = $this->studentOf($agent);

        Livewire::test(EditStudent::class, ['record' => $draft->getRouteKey()])
            ->fillForm(['phone_number' => '+81 90 0000 0000'])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame('+81 90 0000 0000', $draft->fresh()->phone_number);

        Livewire::test(ViewStudent::class, ['record' => $draft->getRouteKey()])
            ->callAction('delete');
        $this->assertSoftDeleted($draft);

        $submitted = $this->studentOf($agent, StudentStatus::Submitted);
        Livewire::test(ViewStudent::class, ['record' => $submitted->getRouteKey()])->assertActionHidden('delete');
    }

    public function test_agent_cannot_preview_documents_of_another_student(): void
    {
        $agent = $this->partner();
        $mine = $this->studentOf($agent);
        $other = $this->student($this->covered);
        $foreign = app(StudentWorkflow::class)->uploadDocument($other, DocumentType::Photo, $this->file(DocumentType::Photo));

        $this->get(route('files.document', $foreign))->assertForbidden();

        Livewire::test(ViewStudent::class, ['record' => $mine->getRouteKey()])
            ->call('mountAction', 'previewDocument', ['document' => $foreign->getKey()])
            ->assertStatus(404);
    }

    // ── Tahap 2: pembayaran (UC-10) & LOA (UC-02) ────────────────────────────

    public function test_agent_pays_each_fee_after_approval(): void
    {
        $admin = $this->superAdmin();
        $agent = $this->partner();
        $this->covered->update(['admission_fee' => 500_000]);
        PaymentAccount::create(['bank_name' => 'Bank BPD Bali', 'account_name' => 'Universitas Udayana', 'va_number' => '8808 0001', 'is_active' => true]);
        $student = $this->studentOf($agent, StudentStatus::Approved);

        $page = Livewire::test(ViewStudent::class, ['record' => $student->getRouteKey()])
            ->assertSee('8808 0001')
            ->assertSee(Rupiah::format(500_000))
            ->callAction('uploadPayment', data: ['file' => $this->file()], arguments: ['type' => PaymentType::AdmissionFee->value])
            ->assertHasNoActionErrors()
            ->assertNotified(__('agent.students.done.payment_uploaded'));

        $payment = $student->payments()->sole();
        $this->assertSame(PaymentStatus::Pending, $payment->status);
        $this->assertSame('8808 0001', $payment->va_number);
        Notification::assertSentTo($admin, PaymentSubmitted::class);

        // KUI menolak → alasan tampil, bukti bisa diganti
        $this->actingAs($admin);
        app(StudentWorkflow::class)->rejectPayment($payment, 'Amount does not match');
        $this->actingAs($agent->user);
        Livewire::test(ViewStudent::class, ['record' => $student->getRouteKey()])
            ->assertSee('Amount does not match')
            ->callAction('uploadPayment', data: ['file' => $this->file()], arguments: ['type' => PaymentType::AdmissionFee->value])
            ->assertHasNoActionErrors();
        $this->assertSame(PaymentStatus::Pending, $payment->fresh()->status);

        // Terverifikasi → tidak bisa diganti, walau dipanggil langsung
        $this->actingAs($admin);
        app(StudentWorkflow::class)->verifyPayment($payment->fresh());
        $this->actingAs($agent->user);
        Livewire::test(ViewStudent::class, ['record' => $student->getRouteKey()])
            ->callAction('uploadPayment', data: ['file' => $this->file()], arguments: ['type' => PaymentType::AdmissionFee->value])
            ->assertNotified(__('workflow.errors.payment_already_verified', ['type' => PaymentType::AdmissionFee->getLabel()]));
    }

    public function test_payment_is_closed_before_approval_and_for_free_programs(): void
    {
        $agent = $this->partner();
        $this->covered->update(['admission_fee' => 500_000]);
        $draft = $this->studentOf($agent);

        Livewire::test(ViewStudent::class, ['record' => $draft->getRouteKey()])
            ->assertSee(__('agent.students.payments.locked_body'))
            ->callAction('uploadPayment', data: ['file' => $this->file()], arguments: ['type' => PaymentType::AdmissionFee->value]);
        $this->assertSame(0, $draft->payments()->count());

        $this->covered->update(['admission_fee' => 0]);
        $approved = $this->studentOf($agent, StudentStatus::Approved);
        Livewire::test(ViewStudent::class, ['record' => $approved->getRouteKey()])
            ->assertSee(__('agent.students.payments.no_fees'));
    }

    public function test_agent_downloads_the_loa_once_issued(): void
    {
        $agent = $this->partner();
        $student = $this->studentOf($agent, StudentStatus::Approved);

        Livewire::test(ViewStudent::class, ['record' => $student->getRouteKey()])->assertActionHidden('downloadLoa');

        $this->actingAs($this->superAdmin());
        $loa = app(StudentWorkflow::class)->issueLoa($student, $this->pdf());

        $this->actingAs($agent->user);
        Livewire::test(ViewStudent::class, ['record' => $student->getRouteKey()])->assertActionVisible('downloadLoa');
        $this->get(route('files.loa', $loa))->assertOk()->assertDownload();
        $this->assertNotNull($loa->fresh()->downloaded_at);

        $this->actingAs($this->agent()->user)->get(route('files.loa', $loa))->assertForbidden();
    }
}
