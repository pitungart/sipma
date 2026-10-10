<?php

namespace Tests\Feature\Agent;

use App\Enums\DocumentType;
use App\Enums\PaymentType;
use App\Enums\StudentStatus;
use App\Filament\Agent\Pages\Dashboard;
use App\Models\Agent;
use App\Models\Program;
use App\Models\Student;
use App\Support\AgentDashboard;
use App\Workflow\MouWorkflow;
use App\Workflow\StudentWorkflow;
use Filament\Facades\Filament;
use Tests\Feature\Workflow\WorkflowTestCase;

/**
 * Dasbor kerja agen (§3 Dashboard agen): kartu angka, perlu tindakan, program MOU.
 */
class DashboardTest extends WorkflowTestCase
{
    private Program $covered;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('agent'));
        $this->covered = $this->program(admissionFee: 500_000);
    }

    private function partner(): Agent
    {
        $agent = $this->agent();
        $mou = app(MouWorkflow::class)->submit($agent, $this->pdf());
        $this->actingAs($this->superAdmin());
        app(MouWorkflow::class)->approve($mou, [$this->covered->id]);
        $this->actingAs($agent->user);

        return $agent->fresh();
    }

    private function studentOf(Agent $agent, StudentStatus $status, array $attributes = []): Student
    {
        $student = $this->student($this->covered, $status);
        $student->update(['user_id' => null, 'agent_id' => $agent->id, ...$attributes]);

        return $student->fresh();
    }

    public function test_onboarding_agent_still_sees_the_onboarding_steps(): void
    {
        $this->actingAs($this->agent()->user)
            ->get(Dashboard::getUrl())
            ->assertOk()
            ->assertSee(__('agent.dashboard.students.locked_body'))
            ->assertDontSee(__('agent.home.actions.title'));
    }

    public function test_workspace_counts_only_the_agents_students(): void
    {
        $agent = $this->partner();
        $this->studentOf($agent, StudentStatus::Draft);
        $this->studentOf($agent, StudentStatus::Submitted);
        $this->studentOf($agent, StudentStatus::InReview);
        $this->studentOf($agent, StudentStatus::Revision);
        $this->studentOf($this->agent(), StudentStatus::Revision); // agen lain

        $cards = collect((new AgentDashboard($agent))->cards())->pluck('value', 'label');

        $this->assertSame(1, $cards[__('agent.home.cards.labels.draft')]);
        $this->assertSame(2, $cards[__('agent.home.cards.labels.submitted')], 'submitted + in review');
        $this->assertSame(1, $cards[__('agent.home.cards.labels.revision')]);
        $this->assertSame(0, $cards[__('agent.home.cards.labels.loa_issued')]);

        $this->get(Dashboard::getUrl())
            ->assertOk()
            ->assertSee(__('agent.home.cards.labels.approved'))
            ->assertSee($this->covered->name)
            ->assertSee(__('agent.students.actions.create'));
    }

    public function test_actions_list_revision_unpaid_ready_drafts_and_loa(): void
    {
        $agent = $this->partner();
        $workflow = app(StudentWorkflow::class);

        $revision = $this->studentOf($agent, StudentStatus::Revision, ['full_name' => 'Revisi Satu', 'revision_note' => 'Fix the photo background']);
        $approved = $this->studentOf($agent, StudentStatus::Approved, ['full_name' => 'Bayar Dua']);
        $ready = $this->studentOf($agent, StudentStatus::Draft, ['full_name' => 'Siap Tiga']);
        foreach (DocumentType::required() as $type) {
            $workflow->uploadDocument($ready, $type, $this->file($type));
        }
        $this->studentOf($agent, StudentStatus::Draft, ['full_name' => 'Belum Lengkap', 'date_of_birth' => null]);

        $actions = (new AgentDashboard($agent))->actions();
        $names = $actions->pluck('name')->all();

        $this->assertSame(['Revisi Satu', 'Bayar Dua', 'Siap Tiga'], $names, 'most urgent first; incomplete drafts are not listed');
        $this->assertSame('Fix the photo background', $actions[0]['reason']);
        $this->assertStringContainsString(__('enums.payment_type.admission_fee'), $actions[1]['reason']);

        // Setelah bukti diunggah, pembayaran hilang dari daftar
        $workflow->submitPayment($approved, PaymentType::AdmissionFee, $this->file());
        $this->assertNotContains('Bayar Dua', (new AgentDashboard($agent))->actions()->pluck('name'));

        // LOA terbit & belum diunduh muncul; setelah diunduh hilang
        $this->actingAs($this->superAdmin());
        $workflow->verifyPayment($approved->payments()->first());
        $loa = $workflow->issueLoa($approved->fresh(), $this->pdf());
        $this->assertContains('Bayar Dua', (new AgentDashboard($agent))->actions()->pluck('name'));
        $this->actingAs($agent->user)->get(route('files.loa', $loa))->assertOk();
        $this->assertNotContains('Bayar Dua', (new AgentDashboard($agent))->actions()->pluck('name'));
    }
}
