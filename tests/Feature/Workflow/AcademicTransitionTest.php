<?php

namespace Tests\Feature\Workflow;

use App\Enums\LoaStatus;
use App\Enums\StudentStatus;
use App\Filament\Admin\Resources\StudentResource as AdminStudentResource;
use App\Filament\Admin\Widgets\StatCards;
use App\Filament\Agent\Resources\StudentResource as AgentStudentResource;
use App\Filament\App\Pages\Dashboard as PortalDashboard;
use App\Filament\App\Pages\MyApplication;
use App\Models\Agent;
use App\Models\Student;
use App\Notifications\StudentEnrolled;
use App\Support\AgentDashboard;
use App\Workflow\MouWorkflow;
use App\Workflow\StatusTimeline;
use App\Workflow\StudentWorkflow;
use App\Workflow\WorkflowException;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

/**
 * TP-0 F1: tahap pasca-LOA — loa_issued → enrolled (Aktif) → completed (Selesai), dan semua layar
 * yang membaca status tetap jalan untuk status baru.
 */
class AcademicTransitionTest extends WorkflowTestCase
{
    private function workflow(): StudentWorkflow
    {
        return app(StudentWorkflow::class);
    }

    public function test_student_is_enrolled_completed_and_reopened(): void
    {
        $admin = $this->superAdmin();
        $owner = $this->studentUser();
        $student = $this->student(status: StudentStatus::LoaIssued, owner: $owner);
        $this->actingAs($admin);

        $this->travelTo('2026-11-02 09:00:00');
        $this->workflow()->enroll($student);

        $student->refresh();
        $this->assertSame(StudentStatus::Enrolled, $student->status);
        $this->assertSame('2026-11-02 09:00:00', $student->enrolled_at->toDateTimeString());
        $this->assertTrue($student->enroller->is($admin));
        Notification::assertSentTo($owner, StudentEnrolled::class);

        $this->travelTo('2027-05-30 15:00:00');
        $this->workflow()->complete($student);
        $this->assertSame(StudentStatus::Completed, $student->fresh()->status);
        $this->assertSame('2027-05-30 15:00:00', $student->fresh()->completed_at->toDateTimeString());

        // Koreksi: kembali Aktif, tanggal aktif tidak berubah
        $this->workflow()->reopen($student);
        $student->refresh();
        $this->assertSame(StudentStatus::Enrolled, $student->status);
        $this->assertNull($student->completed_at);
        $this->assertSame('2026-11-02 09:00:00', $student->enrolled_at->toDateTimeString());
    }

    public function test_agent_is_notified_for_agent_registered_students(): void
    {
        $agent = $this->agent();
        $student = $this->student(status: StudentStatus::LoaIssued);
        $student->update(['user_id' => null, 'agent_id' => $agent->id]);

        $this->actingAs($this->superAdmin());
        $this->workflow()->enroll($student->fresh());

        Notification::assertSentTo($agent->user, StudentEnrolled::class);
    }

    public function test_post_loa_steps_only_follow_their_own_status(): void
    {
        $this->actingAs($this->superAdmin());

        $illegal = [
            'enroll' => [StudentStatus::Draft, StudentStatus::Approved, StudentStatus::Enrolled, StudentStatus::Completed],
            'complete' => [StudentStatus::LoaIssued, StudentStatus::Completed],
            'reopen' => [StudentStatus::LoaIssued, StudentStatus::Enrolled],
        ];

        foreach ($illegal as $step => $statuses) {
            foreach ($statuses as $status) {
                $student = $this->student(status: $status);

                try {
                    $this->workflow()->{$step}($student);
                    $this->fail("{$step} accepted from {$status->value}");
                } catch (WorkflowException) {
                    $this->assertSame($status, $student->fresh()->status);
                }
            }
        }

        Notification::assertNothingSent();
    }

    public function test_status_helpers_gate_the_academic_stage(): void
    {
        $this->assertSame(
            [StudentStatus::LoaIssued, StudentStatus::Enrolled, StudentStatus::Completed],
            StudentStatus::acceptedStatuses(),
        );

        foreach (StudentStatus::cases() as $status) {
            $this->assertSame(in_array($status, StudentStatus::acceptedStatuses(), true), $status->isAccepted(), $status->value);
            $this->assertSame(in_array($status, [StudentStatus::Enrolled, StudentStatus::Completed], true), $status->hasAcademicAccess(), $status->value);
            $this->assertFalse($status->hasAcademicAccess() && $status->isEditable(), 'accepted data is never editable');
        }

        // Garis waktu pendaftaran: semua langkah selesai setelah LOA
        foreach (StudentStatus::acceptedStatuses() as $status) {
            $this->assertSame(['done'], collect(StatusTimeline::for($status))->pluck('state')->unique()->values()->all());
        }
    }

    public function test_every_status_renders_in_the_portal_admin_and_agent_screens(): void
    {
        $admin = $this->superAdmin();
        $agent = $this->partner();
        $program = $agent->allowedPrograms()->first();

        foreach (StudentStatus::cases() as $status) {
            $owner = $this->studentUser();
            $own = $this->student($program, $status, owner: $owner);
            $viaAgent = $this->student($program, $status);
            $viaAgent->update(['user_id' => null, 'agent_id' => $agent->id]);

            if ($status->isAccepted()) {
                $this->withLoa($own);
                $this->withLoa($viaAgent);
            }

            Filament::setCurrentPanel(Filament::getPanel('app'));
            $this->actingAs($owner);
            $this->get(PortalDashboard::getUrl())->assertOk();
            $this->get(MyApplication::getUrl())->assertOk();

            Filament::setCurrentPanel(Filament::getPanel('admin'));
            $this->actingAs($admin);
            $this->get(AdminStudentResource::getUrl('view', ['record' => $own]))->assertOk()->assertSee($status->getLabel());

            Filament::setCurrentPanel(Filament::getPanel('agent'));
            $this->actingAs($agent->user);
            $this->get(AgentStudentResource::getUrl('view', ['record' => $viaAgent]))->assertOk()->assertSee($status->getLabel());
        }

        Filament::setCurrentPanel(Filament::getPanel('app'));
        $this->actingAs(Student::query()->where('status', StudentStatus::Enrolled)->whereNotNull('user_id')->first()->user);
        $this->get(PortalDashboard::getUrl())->assertSee(__('student.home.stages.active.title'));

        Filament::setCurrentPanel(Filament::getPanel('agent'));
        $this->actingAs($agent->user);
        $this->get(AgentStudentResource::getUrl())->assertOk()
            ->assertSee(StudentStatus::Enrolled->getLabel())
            ->assertSee(StudentStatus::Completed->getLabel());

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($admin);
        $this->get(AdminStudentResource::getUrl())->assertOk();
        Livewire::test(StatCards::class)->assertOk();
    }

    public function test_loa_counts_keep_students_who_became_active(): void
    {
        $agent = $this->partner();
        foreach ([StudentStatus::LoaIssued, StudentStatus::Enrolled, StudentStatus::Completed, StudentStatus::Approved] as $status) {
            $student = $this->student($agent->allowedPrograms()->first(), $status);
            $student->update(['user_id' => null, 'agent_id' => $agent->id]);
        }

        Filament::setCurrentPanel(Filament::getPanel('agent'));
        $cards = collect((new AgentDashboard($agent))->cards())->pluck('value', 'label');
        $this->assertSame(3, $cards[__('agent.home.cards.labels.loa_issued')]);
    }

    private function withLoa(Student $student): void
    {
        $student->loa()->create([
            'loa_number' => 'LOA/'.$student->getKey(),
            'file_path' => 'loa/'.$student->getKey().'.pdf',
            'status' => LoaStatus::Uploaded,
            'issued_at' => now(),
        ]);
    }

    private function partner(): Agent
    {
        $agent = $this->agent();
        $mou = app(MouWorkflow::class)->submit($agent, $this->pdf());
        $this->actingAs($this->superAdmin());
        app(MouWorkflow::class)->approve($mou, [$this->program()->id]);

        return $agent->fresh();
    }
}
