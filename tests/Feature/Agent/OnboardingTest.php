<?php

namespace Tests\Feature\Agent;

use App\Enums\MouStatus;
use App\Enums\UserRole;
use App\Filament\Agent\Pages\CompanyProfile;
use App\Filament\Agent\Pages\Dashboard;
use App\Filament\Agent\Pages\ManageMou;
use App\Models\Agent;
use App\Models\Country;
use App\Models\User;
use App\Notifications\MouReviewed;
use App\Notifications\MouSubmitted;
use App\Workflow\AgentOnboarding;
use App\Workflow\MouWorkflow;
use App\Workflow\WorkflowException;
use Filament\Facades\Filament;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\Feature\Workflow\WorkflowTestCase;

/**
 * Onboarding agen (UC-12, UC-13): profil → MOU → persetujuan KUI, dan gerbang pendaftaran.
 */
class OnboardingTest extends WorkflowTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Country::create(['code' => 'JP', 'name_en' => 'Japan', 'name_id' => 'Jepang']);
        Filament::setCurrentPanel(Filament::getPanel('agent'));
    }

    /**
     * Agen seperti hasil /register: hanya nama perusahaan, negara, dan email.
     */
    private function newAgent(): Agent
    {
        $user = User::factory()->create(['role' => UserRole::Agent]);

        return $user->agent()->create(['company_name' => 'Pacific Study', 'email' => $user->email, 'country_code' => 'AU']);
    }

    public function test_new_agent_sees_onboarding_dashboard_with_locked_registration(): void
    {
        $agent = $this->newAgent();

        $this->actingAs($agent->user)
            ->get(Dashboard::getUrl())
            ->assertOk()
            ->assertSee(__('agent.dashboard.stages.profile.title'))
            ->assertSee(__('agent.dashboard.students.locked_body'));

        $this->assertSame(AgentOnboarding::PROFILE, AgentOnboarding::for($agent)->stage());
        $this->assertFalse($agent->canRegisterStudents());
    }

    public function test_agent_completes_profile_and_moves_to_mou_step(): void
    {
        $agent = $this->newAgent();
        $this->actingAs($agent->user);

        Livewire::test(CompanyProfile::class)
            ->fillForm([
                'company_name' => 'Pacific Study Partners',
                'country_code' => 'JP',
                'address' => '2-1 Marunouchi, Tokyo',
                'first_name' => 'Haruto',
                'last_name' => '',
                'email' => '',
                'phone' => '+81 90 1234 5678',
            ])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified(__('agent.company.saved'));

        $agent->refresh();
        $this->assertSame('Pacific Study Partners', $agent->company_name);
        $this->assertSame($agent->user->email, $agent->email, 'empty contact email falls back to the login email');
        $this->assertTrue($agent->isProfileComplete());
        $this->assertSame(AgentOnboarding::MOU, AgentOnboarding::for($agent)->stage());
    }

    public function test_profile_requires_contact_fields(): void
    {
        $this->actingAs($this->newAgent()->user);

        Livewire::test(CompanyProfile::class)
            ->fillForm(['first_name' => '', 'phone' => '', 'address' => ''])
            ->call('save')
            ->assertHasFormErrors(['first_name' => 'required', 'phone' => 'required', 'address' => 'required']);
    }

    public function test_mou_upload_requires_a_complete_profile(): void
    {
        $agent = $this->newAgent();
        $this->actingAs($agent->user);

        Livewire::test(ManageMou::class)
            ->assertActionHidden('upload')
            ->assertActionVisible('completeProfile');

        $this->expectException(WorkflowException::class);
        app(MouWorkflow::class)->submit($agent, $this->pdf());
    }

    public function test_agent_uploads_mou_and_waits_for_kui(): void
    {
        $admin = $this->superAdmin();
        $agent = $this->agent();
        $this->actingAs($agent->user);

        Livewire::test(ManageMou::class)
            ->assertActionVisible('upload')
            ->callAction('upload', data: ['file' => $this->pdf()])
            ->assertHasNoActionErrors()
            ->assertNotified(__('agent.mou.submitted'));

        $this->assertSame(MouStatus::Pending, $agent->latestMou->status);
        Notification::assertSentTo($admin, MouSubmitted::class);

        // Selama diperiksa, MOU tidak bisa diganti
        Livewire::test(ManageMou::class)->assertActionHidden('upload');
        $this->assertSame(AgentOnboarding::PENDING, AgentOnboarding::for($agent->fresh())->stage());
    }

    public function test_rejected_mou_shows_note_and_can_be_reuploaded(): void
    {
        $agent = $this->agent();
        $mou = app(MouWorkflow::class)->submit($agent, $this->pdf());
        $this->actingAs($this->superAdmin());
        app(MouWorkflow::class)->reject($mou, 'Company stamp is missing');

        Notification::assertSentTo($agent->user, MouReviewed::class, function (MouReviewed $n) use ($agent): bool {
            return $n->toDatabase($agent->user)['actions'][0]['url'] === ManageMou::getUrl(panel: 'agent');
        });

        $this->actingAs($agent->user)
            ->get(ManageMou::getUrl())
            ->assertOk()
            ->assertSee('Company stamp is missing');

        Livewire::test(ManageMou::class)
            ->assertActionVisible('upload')
            ->callAction('upload', data: ['file' => $this->pdf()])
            ->assertHasNoActionErrors();

        $this->assertSame(2, $agent->mous()->count(), 'history is kept');
        $this->assertSame(MouStatus::Pending, $agent->fresh()->latestMou->status);
    }

    public function test_approved_mou_unlocks_registration_and_locks_identity_fields(): void
    {
        $agent = $this->agent();
        $mou = app(MouWorkflow::class)->submit($agent, $this->pdf());
        $this->actingAs($this->superAdmin());
        app(MouWorkflow::class)->approve($mou, [$this->program()->id]);

        $agent->refresh();
        $this->assertTrue($agent->canRegisterStudents());
        $this->assertSame(AgentOnboarding::APPROVED, AgentOnboarding::for($agent)->stage());

        $this->actingAs($agent->user)
            ->get(Dashboard::getUrl())
            ->assertOk()
            ->assertSee(__('agent.home.actions.title'))
            ->assertDontSee(__('agent.dashboard.students.locked_body'));

        Livewire::test(CompanyProfile::class)
            ->assertFormFieldIsDisabled('company_name')
            ->assertFormFieldIsDisabled('country_code')
            ->assertFormFieldIsEnabled('phone')
            // Kolom terkunci dikirim paksa lewat Livewire tetap tidak tersimpan
            ->set('data.company_name', 'Renamed Ltd')
            ->set('data.phone', '+61 400 111 222')
            ->call('save')
            ->assertHasNoFormErrors();

        $agent->refresh();
        $this->assertSame('EduBridge', $agent->company_name);
        $this->assertSame('+61 400 111 222', $agent->phone);
    }

    public function test_flow_runs_with_mail_notifications_disabled(): void
    {
        config(['sipma.mail_notifications' => false]);
        Notification::swap(new ChannelManager($this->app)); // pengiriman nyata, bukan fake
        Mail::fake();

        $admin = $this->superAdmin();
        $agent = $this->agent();
        $mou = app(MouWorkflow::class)->submit($agent, $this->pdf());
        $this->actingAs($admin);
        app(MouWorkflow::class)->reject($mou, 'Company stamp is missing');

        // Alur tetap jalan, lonceng tetap terisi, tidak ada email keluar
        $this->assertSame(MouStatus::Rejected, $mou->fresh()->status);
        $this->assertSame(1, $admin->notifications()->count());
        $this->assertSame(1, $agent->user->notifications()->count());
        $this->assertSame(['database'], (new MouReviewed($mou))->via($agent->user));
        Mail::assertNothingOutgoing();
    }

    public function test_agent_cannot_preview_another_agents_mou(): void
    {
        $other = $this->agent();
        $foreign = app(MouWorkflow::class)->submit($other, $this->pdf());
        $agent = $this->agent();
        $this->actingAs($agent->user);

        $this->get(route('files.mou', $foreign))->assertForbidden();

        Livewire::test(ManageMou::class)
            ->call('mountAction', 'previewMou', ['mou' => $foreign->getKey()])
            ->assertStatus(404);
    }

    public function test_mou_template_can_be_downloaded(): void
    {
        $this->actingAs($this->newAgent()->user);

        Livewire::test(ManageMou::class)
            ->callAction('template')
            ->assertFileDownloaded('MOU-template-KUI-Udayana.pdf');
    }

    public function test_other_roles_cannot_open_agent_pages(): void
    {
        $this->actingAs($this->superAdmin())->get(ManageMou::getUrl())->assertForbidden();
        $this->actingAs($this->studentUser())->get(CompanyProfile::getUrl())->assertForbidden();
    }
}
