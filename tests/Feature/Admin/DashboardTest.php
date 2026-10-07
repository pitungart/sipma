<?php

namespace Tests\Feature\Admin;

use App\Enums\StudentStatus;
use App\Enums\UserRole;
use App\Filament\Admin\Pages\Dashboard;
use App\Filament\Admin\Widgets\ApplicantSources;
use App\Filament\Admin\Widgets\ApplicantTrend;
use App\Filament\Admin\Widgets\LatestApplicants;
use App\Filament\Admin\Widgets\ProgramBars;
use App\Filament\Admin\Widgets\RecentActivity;
use App\Filament\Admin\Widgets\StatCards;
use App\Filament\Admin\Widgets\UpcomingAgenda;
use App\Models\AcademicPeriod;
use App\Models\Faculty;
use App\Models\Program;
use App\Models\Student;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_dashboard_shows_the_admission_widgets(): void
    {
        $this->actingAs($this->superAdmin())
            ->get('/admin')
            ->assertOk()
            ->assertSee('Application trend')
            ->assertSee('Applicants per program')
            ->assertSee('Latest applicants')
            ->assertSee('Upcoming agenda')
            ->assertSee('Recent activity')
            ->assertSee('Applicant sources')
            ->assertSee('Export report');
    }

    /**
     * Widget ringkasan milik resource dipasang di halaman daftarnya, bukan di dashboard —
     * discoverWidgets hanya memindai folder Widgets milik panel.
     */
    public function test_dashboard_leaves_out_the_resource_summary_widgets(): void
    {
        $this->actingAs($this->superAdmin())
            ->get('/admin')
            ->assertOk()
            ->assertDontSee('already have a program');
    }

    public function test_program_bars_count_only_what_the_user_may_see(): void
    {
        [$own, $other] = $this->twoFacultiesWithApplicants();
        $admin = $this->facultyAdmin($own->faculty);

        Livewire::actingAs($admin)
            ->test(ProgramBars::class)
            ->assertSee($own->name)
            ->assertDontSee($other->name);

        Livewire::actingAs($this->superAdmin())
            ->test(ProgramBars::class)
            ->assertSee([$own->name, $other->name]);
    }

    public function test_latest_applicants_is_scoped_to_the_faculty(): void
    {
        [$own, $other] = $this->twoFacultiesWithApplicants();
        $admin = $this->facultyAdmin($own->faculty);

        Livewire::actingAs($admin)
            ->test(LatestApplicants::class)
            ->assertSee('Mona Miha')
            ->assertDontSee('John Doe');
    }

    public function test_recent_activity_is_scoped_to_the_faculty(): void
    {
        [$own] = $this->twoFacultiesWithApplicants();
        $admin = $this->facultyAdmin($own->faculty);

        Livewire::actingAs($admin)
            ->test(RecentActivity::class)
            ->assertSee('Mona Miha')
            ->assertDontSee('John Doe');
    }

    public function test_stat_cards_and_sources_count_visible_applicants(): void
    {
        [$own] = $this->twoFacultiesWithApplicants();
        $admin = $this->facultyAdmin($own->faculty);

        Livewire::actingAs($admin)
            ->test(StatCards::class)
            ->assertSeeInOrder(['Applicants', '1', 'Waiting for review', '1']);

        Livewire::actingAs($this->superAdmin())
            ->test(ApplicantSources::class)
            ->assertSee('No partner agency yet');
    }

    public function test_trend_source_filter_switches(): void
    {
        $this->twoFacultiesWithApplicants();

        Livewire::actingAs($this->superAdmin())
            ->test(ApplicantTrend::class)
            ->call('setSource', 'agent')
            ->assertSet('source', 'agent')
            ->call('setSource', 'bogus')
            ->assertSet('source', 'all');
    }

    public function test_agenda_lists_upcoming_period_dates(): void
    {
        $program = $this->program('FIB', 'Fakultas Ilmu Budaya', 'ND-BIPA');

        AcademicPeriod::create([
            'program_id' => $program->id,
            'name' => 'Summer 2027',
            'code' => 'SUMMER-2027',
            'registration_opens_at' => now()->addDays(3),
            'registration_closes_at' => now()->addDays(30),
        ]);

        Livewire::actingAs($this->superAdmin())
            ->test(UpcomingAgenda::class)
            ->assertSeeInOrder(['Summer 2027', 'Registration opens', 'Summer 2027', 'Registration closes']);
    }

    public function test_export_downloads_a_csv(): void
    {
        $this->twoFacultiesWithApplicants();

        Livewire::actingAs($this->superAdmin())
            ->test(Dashboard::class)
            ->callAction('export')
            ->assertFileDownloaded('sipma-pendaftar-'.now()->format('Ymd').'.csv');
    }

    /**
     * @return array{0: Program, 1: Program}
     */
    private function twoFacultiesWithApplicants(): array
    {
        $own = $this->program('FIB', 'Fakultas Ilmu Budaya', 'ND-BIPA');
        $other = $this->program('FT', 'Fakultas Teknik', 'ND-ENG');

        $this->applicant($own, 'Mona Miha');
        $this->applicant($other, 'John Doe');

        return [$own->load('students'), $other->load('students')];
    }

    private function program(string $facultyCode, string $facultyName, string $code): Program
    {
        $faculty = Faculty::firstOrCreate(['code' => $facultyCode], ['name' => $facultyName]);

        return Program::create([
            'faculty_id' => $faculty->id,
            'name' => 'Program '.$code,
            'code' => $code,
        ]);
    }

    private function applicant(Program $program, string $name): Student
    {
        return Student::create([
            'program_id' => $program->id,
            'full_name' => $name,
            'email' => Str::slug($name).'@example.test',
            'passport_number' => 'X'.random_int(100000, 999999),
            'status' => StudentStatus::Submitted,
        ]);
    }

    private function superAdmin(): User
    {
        return User::factory()->create(['role' => UserRole::SuperAdmin]);
    }

    private function facultyAdmin(Faculty $faculty): User
    {
        return User::factory()->create(['role' => UserRole::Admin, 'faculty_id' => $faculty->id]);
    }
}
