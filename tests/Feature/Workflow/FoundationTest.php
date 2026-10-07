<?php

namespace Tests\Feature\Workflow;

use App\Enums\DocumentType;
use App\Enums\LoaStatus;
use App\Enums\MouStatus;
use App\Enums\StudentStatus;
use App\Enums\UserRole;
use App\Models\Student;
use App\Models\User;
use App\Notifications\LoaIssued;
use App\Notifications\MouReviewed;
use App\Notifications\MouSubmitted;
use App\Workflow\MouWorkflow;
use App\Workflow\StatusTimeline;
use App\Workflow\StudentWorkflow;
use App\Workflow\WorkflowException;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\Notification;

class FoundationTest extends WorkflowTestCase
{
    // ── MOU ──────────────────────────────────────────────────────────────────

    public function test_mou_is_reviewed_and_unlocks_student_registration(): void
    {
        $admin = $this->superAdmin();
        $agent = $this->agent();
        $workflow = app(MouWorkflow::class);

        $mou = $workflow->submit($agent, $this->pdf());
        Notification::assertSentTo($admin, MouSubmitted::class);
        $this->assertFalse($agent->user->can('create', Student::class));

        // Satu MOU menunggu saja
        try {
            $workflow->submit($agent, $this->pdf());
            $this->fail('Second pending MOU accepted.');
        } catch (WorkflowException) {
        }

        $this->actingAs($admin);
        $workflow->reject($mou, 'Signature page is missing');
        Notification::assertSentTo($agent->user, MouReviewed::class);

        $second = $workflow->submit($agent->fresh(), $this->pdf());
        $workflow->approve($second);

        $this->assertSame(MouStatus::Approved, $second->fresh()->status);
        $this->assertTrue($agent->user->fresh()->can('create', Student::class));
        $this->assertSame(2, $agent->mous()->count(), 'MOU history is kept');
    }

    // ── Berkas privat ────────────────────────────────────────────────────────

    public function test_private_files_open_only_for_the_owner_and_kui(): void
    {
        $owner = $this->studentUser();
        $student = $this->student(owner: $owner);
        $document = app(StudentWorkflow::class)->uploadDocument($student, DocumentType::Declaration, $this->file());

        $this->get(route('files.document', $document))->assertRedirect(route('login'));

        $this->actingAs($this->studentUser())->get(route('files.document', $document))->assertForbidden();
        $this->actingAs($owner)->get(route('files.document', $document))->assertOk()
            ->assertHeader('content-disposition', 'inline; filename="declaration.pdf"');
        $this->actingAs($this->superAdmin())->get(route('files.document', $document).'?download=1')->assertOk()
            ->assertDownload('declaration.pdf');
    }

    public function test_loa_download_is_recorded_for_the_owner_only(): void
    {
        $owner = $this->studentUser();
        $student = $this->student(owner: $owner, status: StudentStatus::Approved);
        $this->actingAs($admin = $this->superAdmin());
        $loa = app(StudentWorkflow::class)->issueLoa($student, $this->pdf());

        $this->get(route('files.loa', $loa))->assertOk();
        $this->assertNull($loa->fresh()->downloaded_at, 'staff downloads are not recorded');

        $this->actingAs($owner)->get(route('files.loa', $loa))->assertDownload();
        $this->assertSame(LoaStatus::Downloaded, $loa->fresh()->status);
        $this->assertNotNull($loa->fresh()->downloaded_at);
        Notification::assertSentTo($owner, LoaIssued::class);
    }

    // ── Garis waktu, label, bahasa ───────────────────────────────────────────

    public function test_timeline_marks_steps_and_turns_red_on_revision(): void
    {
        $states = fn (StudentStatus $status): array => array_column(StatusTimeline::for($status), 'state', 'key');

        $this->assertSame(['draft' => 'current', 'submitted' => 'upcoming', 'review' => 'upcoming', 'payment' => 'upcoming', 'loa' => 'upcoming'], $states(StudentStatus::Draft));
        $this->assertSame('current', $states(StudentStatus::Approved)['payment']);
        $this->assertSame(['done'], array_values(array_unique($states(StudentStatus::LoaIssued))));

        $review = collect(StatusTimeline::for(StudentStatus::Revision))->firstWhere('key', 'review');
        $this->assertSame(['current', 'danger', 'Changes needed'], [$review['state'], $review['tone'], $review['label']]);

        $html = $this->blade('<x-sipma.status-timeline :status="$status" />', ['status' => StudentStatus::InReview]);
        $html->assertSee('aria-current="step"', false)->assertSee('Review');
    }

    public function test_enum_labels_follow_the_language(): void
    {
        app()->setLocale('id');
        $this->assertSame('Disetujui, menunggu pembayaran', StudentStatus::Approved->getLabel());
        $this->assertSame('Paspor', DocumentType::Passport->getLabel());

        app()->setLocale('en');
        $this->assertSame('Approved, awaiting payment', StudentStatus::Approved->getLabel());
    }

    public function test_notifications_are_written_in_the_recipient_language(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Student, 'locale' => 'id']);
        $student = $this->student(owner: $owner, status: StudentStatus::Approved);

        $mail = (new LoaIssued($student))->toMail($owner);
        $this->assertSame('id', $owner->preferredLocale());

        app()->setLocale('id');
        $this->assertSame('LOA sudah terbit · SIPMA', (new LoaIssued($student))->toMail($owner)->subject);
        $this->assertSame(['database', 'mail'], (new LoaIssued($student))->via($owner));
        $this->assertNotEmpty($mail->actionUrl);
    }

    public function test_switching_language_is_remembered_for_the_signed_in_user(): void
    {
        $user = $this->studentUser();

        $this->actingAs($user)->get(route('locale.switch', 'id'));

        $this->assertSame('id', $user->fresh()->locale);
    }

    // ── Seeder demo ──────────────────────────────────────────────────────────

    public function test_demo_seeder_builds_every_status_with_files(): void
    {
        $this->seed(DemoSeeder::class);

        foreach (StudentStatus::cases() as $status) {
            $this->assertTrue(Student::query()->where('status', $status)->exists(), "no demo student is {$status->value}");
        }

        $document = Student::query()->where('status', StudentStatus::InReview)->first()->documents()->first();
        $this->actingAs(User::query()->where('role', UserRole::SuperAdmin)->first())
            ->get(route('files.document', $document))
            ->assertOk();

        // Dijalankan ulang tidak menggandakan data
        $count = Student::query()->count();
        $this->seed(DemoSeeder::class);
        $this->assertSame($count, Student::query()->count());
    }
}
