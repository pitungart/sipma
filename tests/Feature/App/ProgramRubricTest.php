<?php

namespace Tests\Feature\App;

use App\Enums\MouStatus;
use App\Enums\StudentStatus;
use App\Filament\Admin\Resources\StudentResource\Pages\CreateApplicant;
use App\Filament\Agent\Resources\StudentResource\Pages\CreateStudent;
use App\Filament\App\Pages\EditApplication;
use App\Filament\App\Pages\Programs;
use App\Filament\App\Pages\StartApplication;
use App\Models\Program;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Blade;
use Livewire\Livewire;
use Tests\Feature\Workflow\WorkflowTestCase;

/**
 * K8: rubrik akademik tampil saat memilih program, tanpa nilai dan hanya MK aktif.
 */
class ProgramRubricTest extends WorkflowTestCase
{
    private function packaged(): Program
    {
        $program = $this->program();
        $program->courses()->create(['code' => 'MK101', 'name' => 'Budaya Bali', 'name_en' => 'Balinese Culture', 'credits' => 3, 'semester' => 1]);
        $program->courses()->create(['code' => 'MK102', 'name' => 'Orientasi Kampus', 'credits' => null]);
        $program->courses()->create(['code' => 'MK103', 'name' => 'Seminar Tersembunyi', 'credits' => 4, 'is_active' => false]);

        return $program;
    }

    public function test_component_lists_active_courses_only_with_total_credits(): void
    {
        $html = Blade::render('<x-sipma.program-rubric :program="$program" />', ['program' => $this->packaged()]);

        $this->assertStringContainsString('MK101', $html);
        $this->assertStringContainsString('MK102', $html);
        $this->assertStringNotContainsString('Seminar Tersembunyi', $html);
        $this->assertMatchesRegularExpression('/'.preg_quote(__('workflow.rubric.total'), '/').'.*?>\s*3\s*</s', $html);
    }

    public function test_program_without_package_shows_a_sentence_not_an_empty_table(): void
    {
        $html = Blade::render('<x-sipma.program-rubric :program="$program" />', ['program' => $this->program()]);

        $this->assertStringContainsString(__('workflow.rubric.empty'), $html);
        $this->assertStringNotContainsString('<table', $html);
    }

    public function test_component_follows_the_interface_language(): void
    {
        $program = $this->packaged();

        app()->setLocale('en');
        $this->assertStringContainsString('Balinese Culture', Blade::render('<x-sipma.program-rubric :program="$program" />', ['program' => $program]));

        app()->setLocale('id');
        $this->assertStringContainsString('Budaya Bali', Blade::render('<x-sipma.program-rubric :program="$program" />', ['program' => $program]));
    }

    public function test_catalog_opens_the_rubric_of_an_open_program(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('app'));
        $program = $this->packaged();

        $this->actingAs($this->studentUser());

        Livewire::test(Programs::class)
            ->assertSee(__('student.programs.view_rubric'))
            ->mountAction('rubric', ['program' => $program->getKey()])
            ->assertSee('MK101')
            ->assertDontSee('Seminar Tersembunyi');
    }

    public function test_catalog_does_not_open_a_closed_program(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('app'));
        $program = $this->packaged();
        $program->update(['is_active' => false]);

        $this->actingAs($this->studentUser());

        Livewire::test(Programs::class)
            ->call('mountAction', 'rubric', ['program' => $program->getKey()])
            ->assertStatus(404);
    }

    public function test_start_and_edit_application_show_the_chosen_programs_rubric(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('app'));
        $program = $this->packaged();
        $empty = $this->program();
        $user = $this->studentUser();

        $this->actingAs($user);

        Livewire::test(StartApplication::class)
            ->assertDontSee('MK101')
            ->fillForm(['program_id' => $program->getKey()])
            ->assertSee('MK101')
            ->assertSee(__('workflow.rubric.title'))
            ->fillForm(['program_id' => $empty->getKey()])
            ->assertDontSee('MK101')
            ->assertSee(__('workflow.rubric.empty'));

        $this->student($program, StudentStatus::Draft, $user);

        Livewire::test(EditApplication::class)->assertSee('MK101');
    }

    public function test_agent_and_admin_forms_show_the_rubric(): void
    {
        $program = $this->packaged();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->superAdmin());

        Livewire::test(CreateApplicant::class)
            ->fillForm(['program_id' => $program->getKey()])
            ->assertSee('MK101')
            ->assertDontSee('Seminar Tersembunyi');

        Filament::setCurrentPanel(Filament::getPanel('agent'));
        $agent = $this->agent();
        $mou = $agent->mous()->create(['file_path' => 'mou.pdf', 'status' => MouStatus::Approved]);
        $mou->programs()->attach($program);

        $this->actingAs($agent->user->fresh());

        // Satu program dalam MOU sudah terpilih otomatis, jadi rubriknya langsung tampil
        Livewire::test(CreateStudent::class)->assertSee('MK101');
    }
}
