<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Filament\Admin\Resources\AcademicPeriodResource;
use App\Filament\Admin\Resources\CountryResource;
use App\Filament\Admin\Resources\FacultyResource;
use App\Filament\Admin\Resources\PaymentAccountResource;
use App\Filament\Admin\Resources\ProgramResource;
use App\Filament\Admin\Resources\UserResource;
use App\Models\Country;
use App\Models\Faculty;
use App\Models\Program;
use App\Models\User;
use Database\Seeders\CountrySeeder;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Tables\Actions\DeleteAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MasterDataPanelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_super_admin_sees_every_master_data_resource(): void
    {
        $this->actingAs($this->superAdmin());

        foreach ($this->resources() as $resource) {
            $this->assertTrue($resource::canViewAny(), $resource.' should be visible to the super admin');
        }
    }

    public function test_faculty_admin_only_manages_programs_and_periods(): void
    {
        $this->actingAs($this->facultyAdmin());

        $this->assertTrue(ProgramResource::canViewAny());
        $this->assertTrue(AcademicPeriodResource::canViewAny());

        $this->assertFalse(FacultyResource::canViewAny());
        $this->assertFalse(UserResource::canViewAny());
        $this->assertFalse(PaymentAccountResource::canViewAny());
        $this->assertFalse(CountryResource::canViewAny());
    }

    public function test_faculty_admin_only_sees_programs_of_their_own_faculty(): void
    {
        $admin = $this->facultyAdmin();
        $own = $this->program($admin->faculty, 'ND-OWN');
        $other = $this->program(Faculty::create(['name' => 'Fakultas Teknik', 'code' => 'FT']), 'ND-OTHER');

        Livewire::actingAs($admin)
            ->test(ProgramResource\Pages\ManagePrograms::class)
            ->assertCanSeeTableRecords([$own])
            ->assertCanNotSeeTableRecords([$other]);
    }

    public function test_faculty_admin_cannot_move_a_program_to_another_faculty(): void
    {
        $admin = $this->facultyAdmin();
        $otherFaculty = Faculty::create(['name' => 'Fakultas Teknik', 'code' => 'FT']);

        Livewire::actingAs($admin)
            ->test(ProgramResource\Pages\ManagePrograms::class)
            ->callAction(CreateAction::class, data: [
                'faculty_id' => $otherFaculty->id,
                'name' => 'Selundupan',
                'code' => 'ND-X',
            ])
            ->assertHasActionErrors(['faculty_id']);

        $this->assertDatabaseCount('programs', 0);
    }

    public function test_super_admin_creates_a_program_with_rupiah_fees(): void
    {
        $faculty = Faculty::create(['name' => 'Fakultas Ilmu Budaya', 'code' => 'FIB']);

        Livewire::actingAs($this->superAdmin())
            ->test(ProgramResource\Pages\ManagePrograms::class)
            ->callAction(CreateAction::class, data: [
                'faculty_id' => $faculty->id,
                'name' => 'Indonesian Language for Foreign Speakers',
                'code' => 'nd-bipa',
                'admission_fee' => 500000,
                'tuition_fee' => 7500000,
            ])
            ->assertHasNoActionErrors();

        $program = Program::firstOrFail();

        $this->assertSame('ND-BIPA', $program->code); // kode selalu huruf besar
        $this->assertSame('indonesian-language-for-foreign-speakers', $program->slug);
        $this->assertSame('500000.00', $program->admission_fee);
    }

    public function test_period_closing_date_cannot_precede_the_opening_date(): void
    {
        $admin = $this->facultyAdmin();
        $program = $this->program($admin->faculty, 'ND-OWN');

        Livewire::actingAs($admin)
            ->test(AcademicPeriodResource\Pages\ManageAcademicPeriods::class)
            ->callAction(CreateAction::class, data: [
                'program_id' => $program->id,
                'name' => 'Summer 2026',
                'code' => 'summer-2026',
                'registration_opens_at' => '2026-03-01',
                'registration_closes_at' => '2026-02-01',
            ])
            ->assertHasActionErrors(['registration_closes_at']);
    }

    public function test_administrator_list_leaves_out_students_and_agents(): void
    {
        $superAdmin = $this->superAdmin();
        $facultyAdmin = $this->facultyAdmin();
        $student = User::factory()->create(['role' => UserRole::Student]);
        $agent = User::factory()->create(['role' => UserRole::Agent]);

        Livewire::actingAs($superAdmin)
            ->test(UserResource\Pages\ManageUsers::class)
            ->assertCanSeeTableRecords([$superAdmin, $facultyAdmin])
            ->assertCanNotSeeTableRecords([$student, $agent]);
    }

    public function test_faculty_is_required_for_a_faculty_administrator(): void
    {
        Livewire::actingAs($this->superAdmin())
            ->test(UserResource\Pages\ManageUsers::class)
            ->callAction(CreateAction::class, data: [
                'name' => 'Admin FIB',
                'email' => 'admin.fib@unud.test',
                'role' => UserRole::Admin->value,
                'password' => 'rahasia-sekali',
            ])
            ->assertHasActionErrors(['faculty_id']);
    }

    public function test_countries_are_read_only_in_the_panel(): void
    {
        $this->assertFalse(CountryResource::canCreate());
        $this->assertSame(['index'], array_keys(CountryResource::getPages()));
    }

    public function test_country_list_renders_and_can_be_switched_off(): void
    {
        $this->seed(CountrySeeder::class);

        $this->actingAs($this->superAdmin())
            ->get('/admin/countries')
            ->assertOk()
            ->assertSee('Andorra');

        $indonesia = Country::find('ID');

        Livewire::actingAs($this->superAdmin())
            ->test(CountryResource\Pages\ManageCountries::class)
            ->set('tableSearch', 'Indonesia')
            ->assertCanSeeTableRecords([$indonesia])
            ->call('updateTableColumnState', 'is_active', $indonesia->code, false);

        // Negara nonaktif hilang dari pilihan di formulir pendaftaran.
        $this->assertArrayNotHasKey('ID', Country::options());
    }

    public function test_admin_panel_is_in_english_by_default(): void
    {
        // Header kolom hanya dirender bila tabelnya berisi.
        $this->program(Faculty::create(['name' => 'Fakultas Ilmu Budaya', 'code' => 'FIB']), 'ND-BIPA');

        $html = $this->actingAs($this->superAdmin())
            ->get('/admin/programs')
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression('/<html\s+lang="en"/', $html);
        $this->assertStringContainsString('Master data', $html);
        $this->assertStringContainsString('Admission fee', $html);
    }

    /**
     * Satu bahasa per test: Filament membangun label grup navigasi sekali per instance aplikasi
     * (binding scoped), sedangkan di produksi tiap request memakai instance baru.
     */
    public function test_admin_panel_follows_the_chosen_language(): void
    {
        // Header kolom hanya dirender bila tabelnya berisi.
        $this->program(Faculty::create(['name' => 'Fakultas Ilmu Budaya', 'code' => 'FIB']), 'ND-BIPA');

        $html = $this->actingAs($this->superAdmin())
            ->withCookie('locale', 'id')
            ->get('/admin/programs')
            ->assertOk()
            ->getContent();

        // Bukan sekadar lang="id": atribut itu juga ada pada tautan di pemilih bahasa.
        $this->assertMatchesRegularExpression('/<html\s+lang="id"/', $html);
        $this->assertStringContainsString('Data master', $html);
        $this->assertStringContainsString('Biaya pendaftaran', $html);
    }

    public function test_master_pages_show_entity_tabs_for_what_the_role_may_open(): void
    {
        $html = $this->actingAs($this->facultyAdmin())
            ->get('/admin/programs')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('sipma-master-tabs', $html);
        $this->assertStringContainsString('/admin/academic-periods', $html);
        $this->assertStringNotContainsString('/admin/faculties', $html);
    }

    public function test_status_switch_saves_immediately(): void
    {
        $program = $this->program(Faculty::create(['name' => 'Fakultas Ilmu Budaya', 'code' => 'FIB']), 'ND-BIPA');

        Livewire::actingAs($this->superAdmin())
            ->test(ProgramResource\Pages\ManagePrograms::class)
            ->call('updateTableColumnState', 'is_active', $program->getKey(), false);

        $this->assertFalse($program->fresh()->is_active);
    }

    public function test_faculty_with_programs_cannot_be_deleted(): void
    {
        $faculty = Faculty::create(['name' => 'Fakultas Ilmu Budaya', 'code' => 'FIB']);
        $empty = Faculty::create(['name' => 'Fakultas Teknik', 'code' => 'FT']);
        $this->program($faculty, 'ND-BIPA');

        Livewire::actingAs($this->superAdmin())
            ->test(FacultyResource\Pages\ManageFaculties::class)
            ->assertTableActionDisabled(DeleteAction::class, $faculty->getKey())
            ->callTableAction(DeleteAction::class, $empty->getKey());

        $this->assertModelMissing($empty);
    }

    public function test_every_master_table_shows_ten_rows_by_default(): void
    {
        $this->actingAs($this->superAdmin());

        $pages = [
            FacultyResource\Pages\ManageFaculties::class,
            ProgramResource\Pages\ManagePrograms::class,
            AcademicPeriodResource\Pages\ManageAcademicPeriods::class,
            PaymentAccountResource\Pages\ManagePaymentAccounts::class,
            CountryResource\Pages\ManageCountries::class,
            UserResource\Pages\ManageUsers::class,
        ];

        foreach ($pages as $page) {
            $this->assertSame(10, Livewire::test($page)->instance()->getTableRecordsPerPage(), $page);
        }
    }

    public function test_faculty_admin_cannot_open_the_country_page(): void
    {
        $this->actingAs($this->facultyAdmin())
            ->get('/admin/countries')
            ->assertForbidden();
    }

    /**
     * @return list<class-string<\Filament\Resources\Resource>>
     */
    private function resources(): array
    {
        return [
            FacultyResource::class,
            ProgramResource::class,
            AcademicPeriodResource::class,
            PaymentAccountResource::class,
            CountryResource::class,
            UserResource::class,
        ];
    }

    private function superAdmin(): User
    {
        return User::factory()->create(['role' => UserRole::SuperAdmin]);
    }

    private function facultyAdmin(): User
    {
        $faculty = Faculty::create(['name' => 'Fakultas Ilmu Budaya', 'code' => 'FIB']);

        return User::factory()->create(['role' => UserRole::Admin, 'faculty_id' => $faculty->id]);
    }

    private function program(Faculty $faculty, string $code): Program
    {
        return Program::create([
            'faculty_id' => $faculty->id,
            'name' => 'Program '.$code,
            'code' => $code,
        ]);
    }
}
