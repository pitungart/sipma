<?php

namespace Tests\Feature\MasterData;

use App\Enums\UserRole;
use App\Filament\Admin\Resources\LecturerResource;
use App\Filament\Admin\Resources\LecturerResource\Pages\ManageLecturers;
use App\Models\Faculty;
use App\Models\Lecturer;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\RestoreAction;
use Filament\Tables\Filters\TrashedFilter;
use Illuminate\Database\QueryException;
use Livewire\Livewire;
use Tests\Feature\Workflow\WorkflowTestCase;

/**
 * TP-4 F1: Master Dosen.
 */
class LecturersTest extends WorkflowTestCase
{
    private Faculty $fib;

    private Faculty $fk;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->fib = Faculty::create(['code' => 'FIB', 'name' => 'Fakultas Ilmu Budaya']);
        $this->fk = Faculty::create(['code' => 'FK', 'name' => 'Fakultas Kedokteran']);
    }

    private function lecturer(Faculty $faculty, string $name = 'Dr. Budi', ?string $nip = null, array $attributes = []): Lecturer
    {
        return Lecturer::create(['faculty_id' => $faculty->id, 'name' => $name, 'nip' => $nip, ...$attributes]);
    }

    private function facultyAdmin(Faculty $faculty): User
    {
        return User::factory()->create(['role' => UserRole::Admin, 'faculty_id' => $faculty->id]);
    }

    public function test_kui_creates_a_lecturer_and_chooses_the_faculty(): void
    {
        $this->actingAs($this->superAdmin());

        Livewire::test(ManageLecturers::class)
            ->callAction('create', [
                'name' => 'Dr. Budi Santoso, M.T.',
                'faculty_id' => $this->fk->id,
                'nip' => ' 198001012005011001 ',
                'email' => 'budi@unud.test',
                'phone' => '+62 361 000',
            ])
            ->assertHasNoActionErrors();

        $lecturer = Lecturer::query()->sole();
        $this->assertSame($this->fk->id, $lecturer->faculty_id);
        $this->assertSame('198001012005011001', $lecturer->nip);
        $this->assertTrue($lecturer->is_active);
    }

    public function test_only_name_and_faculty_are_required(): void
    {
        $this->actingAs($this->superAdmin());

        Livewire::test(ManageLecturers::class)
            ->callAction('create', ['name' => '', 'faculty_id' => null])
            ->assertHasActionErrors(['name' => 'required', 'faculty_id' => 'required']);

        foreach (['Dosen Satu', 'Dosen Dua'] as $name) {
            Livewire::test(ManageLecturers::class)
                ->callAction('create', ['name' => $name, 'faculty_id' => $this->fib->id, 'nip' => ''])
                ->assertHasNoActionErrors();
        }

        // Dua dosen tanpa NIP tidak bentrok
        $this->assertSame(2, Lecturer::query()->whereNull('nip')->count());
    }

    public function test_email_must_be_valid(): void
    {
        $this->actingAs($this->superAdmin());

        Livewire::test(ManageLecturers::class)
            ->callAction('create', ['name' => 'A', 'faculty_id' => $this->fib->id, 'email' => 'bukan-email'])
            ->assertHasActionErrors(['email']);
    }

    public function test_nip_is_unique_across_faculties_and_while_deleted(): void
    {
        $lecturer = $this->lecturer($this->fib, nip: 'NIP-1');

        $this->actingAs($this->superAdmin());

        Livewire::test(ManageLecturers::class)
            ->callAction('create', ['name' => 'Lain', 'faculty_id' => $this->fk->id, 'nip' => 'NIP-1'])
            ->assertHasActionErrors(['nip' => 'unique']);

        Livewire::test(ManageLecturers::class)
            ->callTableAction(EditAction::class, $lecturer, ['name' => 'Tetap', 'nip' => 'NIP-1'])
            ->assertHasNoTableActionErrors();

        Livewire::test(ManageLecturers::class)->callTableAction(DeleteAction::class, $lecturer->fresh());
        $this->assertSoftDeleted($lecturer);

        Livewire::test(ManageLecturers::class)
            ->callAction('create', ['name' => 'Lain', 'faculty_id' => $this->fk->id, 'nip' => 'NIP-1'])
            ->assertHasActionErrors(['nip' => 'unique']);

        Livewire::test(ManageLecturers::class)
            ->filterTable(TrashedFilter::class, true)
            ->assertCanSeeTableRecords([$lecturer])
            ->callTableAction(RestoreAction::class, $lecturer->fresh());

        $this->assertNotSoftDeleted($lecturer);
    }

    public function test_faculty_admin_sees_and_manages_only_their_faculty(): void
    {
        $own = $this->lecturer($this->fib, 'Dosen Sendiri');
        $other = $this->lecturer($this->fk, 'Dosen Lain');
        $admin = $this->facultyAdmin($this->fib);

        $this->actingAs($admin);

        Livewire::test(ManageLecturers::class)
            ->assertCanSeeTableRecords([$own])
            ->assertCanNotSeeTableRecords([$other])
            ->assertTableFilterHidden('faculty_id');

        $this->assertTrue($admin->can('update', $own));
        $this->assertFalse($admin->can('view', $other));
        $this->assertFalse($admin->can('update', $other));
        $this->assertFalse($admin->can('delete', $other));
        $this->assertFalse($admin->can('restore', $other));
        $this->assertFalse($admin->can('forceDelete', $own));
    }

    public function test_faculty_admin_faculty_is_filled_and_cannot_be_forced(): void
    {
        $own = $this->lecturer($this->fib);

        $this->actingAs($this->facultyAdmin($this->fib));

        Livewire::test(ManageLecturers::class)
            ->mountAction('create')
            ->assertSet('mountedActionsData.0.faculty_id', $this->fib->id);

        Livewire::test(ManageLecturers::class)
            ->callAction('create', ['name' => 'Baru'])
            ->assertHasNoActionErrors();

        $this->assertSame($this->fib->id, Lecturer::query()->where('name', 'Baru')->value('faculty_id'));

        Livewire::test(ManageLecturers::class)
            ->callAction('create', ['name' => 'Selundupan', 'faculty_id' => $this->fk->id])
            ->assertHasActionErrors(['faculty_id']);

        Livewire::test(ManageLecturers::class)
            ->callTableAction(EditAction::class, $own, ['faculty_id' => $this->fk->id])
            ->assertHasTableActionErrors(['faculty_id']);

        $this->assertSame($this->fib->id, $own->fresh()->faculty_id);
    }

    public function test_faculty_admin_without_faculty_has_no_access(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'faculty_id' => null]);

        $this->assertFalse($admin->can('viewAny', Lecturer::class));
        $this->assertSame(0, Lecturer::query()->visibleTo($admin)->count());
    }

    public function test_agents_and_students_are_refused(): void
    {
        $lecturer = $this->lecturer($this->fib);

        foreach ([$this->agent()->user, $this->studentUser()] as $user) {
            foreach (['viewAny', 'create'] as $ability) {
                $this->assertFalse($user->can($ability, Lecturer::class));
            }
            foreach (['view', 'update', 'delete', 'restore', 'forceDelete'] as $ability) {
                $this->assertFalse($user->can($ability, $lecturer));
            }

            $this->assertSame(0, Lecturer::query()->visibleTo($user)->count());
            $this->actingAs($user)->get(LecturerResource::getUrl())->assertForbidden();
        }
    }

    public function test_search_finds_name_nip_and_email(): void
    {
        $byName = $this->lecturer($this->fib, 'Ayu Lestari', 'N-1', ['email' => 'a@unud.test']);
        $byNip = $this->lecturer($this->fib, 'Bagus', 'N-22');
        $byEmail = $this->lecturer($this->fib, 'Citra', null, ['email' => 'citra@unud.test']);

        $this->actingAs($this->superAdmin());

        Livewire::test(ManageLecturers::class)->searchTable('Ayu')->assertCanSeeTableRecords([$byName])->assertCanNotSeeTableRecords([$byNip, $byEmail]);
        Livewire::test(ManageLecturers::class)->searchTable('N-22')->assertCanSeeTableRecords([$byNip])->assertCanNotSeeTableRecords([$byName]);
        Livewire::test(ManageLecturers::class)->searchTable('citra@')->assertCanSeeTableRecords([$byEmail])->assertCanNotSeeTableRecords([$byName]);
    }

    public function test_lecturers_appear_in_the_master_data_tabs(): void
    {
        $this->actingAs($this->superAdmin());

        $this->assertContains(LecturerResource::getPluralModelLabel(), LecturerResource::masterTabs()->pluck('label'));
        $this->assertSame(__('admin.groups.master_data'), LecturerResource::getNavigationGroup());
    }

    public function test_faculty_with_lecturers_cannot_be_force_deleted(): void
    {
        $this->lecturer($this->fib);

        $this->expectException(QueryException::class);
        $this->fib->forceDelete();
    }
}
