<?php

namespace Tests\Feature\Admin;

use App\Enums\StudentStatus;
use App\Enums\TeachingRole;
use App\Enums\UserRole;
use App\Filament\Admin\Resources\AcademicStudentResource\Pages\ViewAcademicStudent;
use App\Filament\Admin\Resources\CourseResource\Pages\ManageCourses;
use App\Filament\Admin\Resources\TeachingDecreeResource\Pages\ListTeachingDecrees;
use App\Filament\App\Pages\AcademicRubric;
use App\Filament\App\Pages\Programs;
use App\Models\Course;
use App\Models\Faculty;
use App\Models\Lecturer;
use App\Models\Program;
use App\Models\Student;
use App\Models\TeachingDecree;
use App\Models\User;
use App\Support\TeachingDecreeExport;
use App\Support\TeachingRecords;
use App\Workflow\AcademicRecordWorkflow;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Feature\Workflow\WorkflowTestCase;

/**
 * TP-4 F3: SK Mengajar di tab mahasiswa, rubrik, Paket mata kuliah, portal, dan rekap.
 */
class TeachingIntegrationTest extends WorkflowTestCase
{
    private Program $program;

    private Course $course;

    private Lecturer $lecturer;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->program = $this->program();
        $this->course = $this->program->courses()->create(['code' => 'BUD1', 'name' => 'Budaya Bali', 'credits' => 3]);
        $this->lecturer = Lecturer::create([
            'faculty_id' => $this->program->faculty_id,
            'name' => 'Dr. Ayu Lestari',
            'nip' => 'NIP-RAHASIA',
            'email' => 'ayu.rahasia@unud.test',
            'phone' => '+62 000 RAHASIA',
        ]);
    }

    private function enrolled(?string $enrolledAt = '2026-06-01', ?string $completedAt = null, ?Program $program = null): Student
    {
        $student = $this->student($program ?? $this->program, StudentStatus::Enrolled);
        app(AcademicRecordWorkflow::class)->applyPackage($student);
        $student->update([
            'enrolled_at' => $enrolledAt,
            'completed_at' => $completedAt,
            'status' => $completedAt ? StudentStatus::Completed : StudentStatus::Enrolled,
        ]);

        return $student->fresh();
    }

    private function decree(string $start = '2026-01-01', string $end = '2026-12-31', array $attributes = [], ?Course $course = null, TeachingRole $role = TeachingRole::Coordinator): TeachingDecree
    {
        $course ??= $this->course;

        $decree = TeachingDecree::create([
            'faculty_id' => $course->program->faculty_id,
            'decree_number' => 'SK/'.uniqid(),
            'decree_date' => $start,
            'period_start' => $start,
            'period_end' => $end,
            ...$attributes,
        ]);

        $decree->assignments()->create(['lecturer_id' => $this->lecturer->id, 'course_id' => $course->id, 'role' => $role]);

        return $decree;
    }

    private function csv(Builder $query): string
    {
        ob_start();
        TeachingDecreeExport::download($query)->sendContent();

        return (string) ob_get_clean();
    }

    public function test_decree_matches_only_when_the_period_overlaps_the_active_time(): void
    {
        $before = $this->decree('2025-01-01', '2026-05-31');
        $touching = $this->decree('2025-01-01', '2026-06-01');
        $during = $this->decree('2026-07-01', '2026-08-01');
        $after = $this->decree('2026-09-02', '2027-01-01');

        $done = $this->enrolled('2026-06-01', '2026-09-01');
        $ids = TeachingRecords::forStudent($done)->pluck('teaching_decree_id');

        $this->assertNotContains($before->id, $ids);
        $this->assertContains($touching->id, $ids);
        $this->assertContains($during->id, $ids);
        $this->assertNotContains($after->id, $ids);

        // Masih Aktif: tanpa batas akhir
        $this->assertContains($after->id, TeachingRecords::forStudent($this->enrolled('2026-06-01'))->pluck('teaching_decree_id'));
    }

    public function test_dropped_course_deleted_decree_and_other_courses_do_not_match(): void
    {
        $student = $this->enrolled();
        $this->decree();

        $other = $this->program->courses()->create(['code' => 'LAIN', 'name' => 'MK lain']);
        $unrelated = $this->decree(course: $other);

        // MK lain ditambahkan setelah applyPackage, jadi mahasiswa ini tidak menempuhnya
        $this->assertNotContains($unrelated->id, TeachingRecords::forStudent($student)->pluck('teaching_decree_id'));

        app(AcademicRecordWorkflow::class)->drop($student->studentCourses()->first());
        $this->assertTrue(TeachingRecords::forStudent($student)->isEmpty());

        app(AcademicRecordWorkflow::class)->restore($student->studentCourses()->first());
        $this->assertCount(1, TeachingRecords::forStudent($student));

        TeachingDecree::query()->whereHas('assignments', fn ($q) => $q->where('course_id', $this->course->id))->first()->delete();
        $this->assertTrue(TeachingRecords::forStudent($student)->isEmpty());
    }

    public function test_student_not_yet_active_has_no_decrees(): void
    {
        $this->decree();
        $student = $this->student($this->program, StudentStatus::LoaIssued);

        $this->assertTrue(TeachingRecords::forStudent($student)->isEmpty());

        $this->actingAs($this->superAdmin());

        Livewire::test(ViewAcademicStudent::class, ['record' => $student->getKey()])
            ->assertSee(__('admin.teaching_decree.student.not_active'));
    }

    public function test_tab_lists_the_decree_and_rubric_names_the_teacher(): void
    {
        $decree = $this->decree(attributes: ['decree_number' => 'SK/TAMPIL']);
        $student = $this->enrolled();

        $this->actingAs($this->superAdmin());

        Livewire::test(ViewAcademicStudent::class, ['record' => $student->getKey()])
            ->assertSee('SK/TAMPIL')
            ->assertSee('Dr. Ayu Lestari ('.TeachingRole::Coordinator->getLabel().')');

        $this->assertNotNull($decree->fresh());
    }

    public function test_tab_shows_the_empty_hint_without_a_decree(): void
    {
        $this->actingAs($this->superAdmin());

        Livewire::test(ViewAcademicStudent::class, ['record' => $this->enrolled()->getKey()])
            ->assertSee(__('admin.teaching_decree.student.empty_hint'));
    }

    public function test_cross_faculty_admin_sees_names_but_no_file_link(): void
    {
        // MK milik fakultas lain, ditempuh mahasiswa fakultas ini
        $otherProgram = $this->program();
        $otherProgram->update(['faculty_id' => Faculty::create(['code' => 'FK', 'name' => 'Fakultas Kedokteran'])->id]);
        $foreignCourse = $otherProgram->courses()->create(['code' => 'MED1', 'name' => 'Klinik']);

        Storage::disk('local')->put('teaching-decrees/x/sk.pdf', '%PDF');
        $decree = $this->decree(attributes: ['decree_number' => 'SK/LINTAS', 'document' => 'teaching-decrees/x/sk.pdf'], course: $foreignCourse);

        $student = $this->enrolled();
        app(AcademicRecordWorkflow::class)->addCourse($student, $foreignCourse);

        $url = route('files.teaching-decree', ['decree' => $decree, 'download' => 1]);
        $admin = User::factory()->create(['role' => UserRole::Admin, 'faculty_id' => $this->program->faculty_id]);

        $this->actingAs($admin);

        Livewire::test(ViewAcademicStudent::class, ['record' => $student->getKey()])
            ->assertSee('SK/LINTAS')
            ->assertSee('Dr. Ayu Lestari')
            ->assertDontSee(htmlspecialchars($url), false);

        $this->get($url)->assertForbidden();

        $this->actingAs($this->superAdmin());

        Livewire::test(ViewAcademicStudent::class, ['record' => $student->getKey()])
            ->assertSee(htmlspecialchars($url), false);
    }

    public function test_portal_shows_name_and_role_only(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('app'));

        Storage::disk('local')->put('teaching-decrees/y/sk.pdf', '%PDF');
        $decree = $this->decree(attributes: ['decree_number' => 'SK/PORTAL', 'document' => 'teaching-decrees/y/sk.pdf']);
        $student = $this->enrolled();

        $this->actingAs($student->user);

        Livewire::test(AcademicRubric::class)
            ->assertSee('Dr. Ayu Lestari ('.TeachingRole::Coordinator->getLabel().')')
            ->assertDontSee('NIP-RAHASIA')
            ->assertDontSee('ayu.rahasia@unud.test')
            ->assertDontSee('RAHASIA')
            ->assertDontSee('SK/PORTAL')
            ->assertDontSee('teaching-decrees');

        $this->get(route('files.teaching-decree', ['decree' => $decree, 'download' => 1]))->assertForbidden();
    }

    public function test_program_catalog_rubric_does_not_show_teachers(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('app'));
        $this->decree(start: now()->subMonth()->toDateString(), end: now()->addMonth()->toDateString());

        $this->actingAs($this->studentUser());

        Livewire::test(Programs::class)
            ->mountAction('rubric', ['program' => $this->program->getKey()])
            ->assertSee('BUD1')
            ->assertDontSee('Dr. Ayu Lestari');
    }

    public function test_course_list_shows_only_teachers_from_decrees_in_effect_today(): void
    {
        $withCurrent = $this->course;
        $withEnded = $this->program->courses()->create(['code' => 'OLD', 'name' => 'SK lama']);
        $none = $this->program->courses()->create(['code' => 'NONE', 'name' => 'Tanpa SK']);

        $this->decree(now()->subDay()->toDateString(), now()->addDay()->toDateString(), course: $withCurrent);
        $this->decree(now()->subYear()->toDateString(), now()->subDay()->toDateString(), course: $withEnded);

        $this->actingAs($this->superAdmin());

        Livewire::test(ManageCourses::class)
            ->assertTableColumnStateSet('teachers', 'Dr. Ayu Lestari ('.TeachingRole::Coordinator->getLabel().')', $withCurrent->getKey())
            ->assertTableColumnStateSet('teachers', null, $withEnded->getKey())
            ->assertTableColumnStateSet('teachers', null, $none->getKey());
    }

    public function test_lecturer_listed_on_two_decrees_is_named_once(): void
    {
        $this->decree('2026-01-01', '2026-06-30');
        $this->decree('2026-07-01', '2026-12-31', role: TeachingRole::Lecturer);

        $student = $this->enrolled();
        $text = TeachingRecords::teachersByCourse(TeachingRecords::forStudent($student))[$this->course->id];

        $this->assertSame(1, substr_count($text, 'Dr. Ayu Lestari'));
    }

    public function test_export_has_one_row_per_assignment_and_counts_students(): void
    {
        $decree = $this->decree('2026-01-01', '2026-12-31', ['decree_number' => 'SK/REKAP']);
        $this->enrolled('2026-06-01');
        $this->enrolled('2026-02-01', '2026-03-01');
        $this->enrolled('2025-01-01', '2025-06-01'); // sudah selesai sebelum SK
        $this->enrolled('2027-02-01'); // baru aktif setelah SK

        $dropped = $this->enrolled('2026-06-01');
        app(AcademicRecordWorkflow::class)->drop($dropped->studentCourses()->first());

        $this->assertSame(2, TeachingDecreeExport::students($decree, $this->course->id));

        $csv = $this->csv(TeachingDecree::query());
        $lines = array_values(array_filter(explode("\n", $csv)));

        $this->assertCount(2, $lines);
        $row = str_getcsv($lines[1]);
        $this->assertSame('SK/REKAP', $row[0]);
        $this->assertSame('Dr. Ayu Lestari', $row[5]);
        $this->assertSame('NIP-RAHASIA', $row[6]);
        $this->assertSame('BUD1', $row[7]);
        $this->assertSame('2', $row[11]);
    }

    public function test_export_follows_faculty_and_filters(): void
    {
        $own = $this->decree(attributes: ['decree_number' => 'SK/SENDIRI']);

        $other = $this->program();
        $other->update(['faculty_id' => Faculty::create(['code' => 'FK', 'name' => 'Fakultas Kedokteran'])->id]);
        $otherCourse = $other->courses()->create(['code' => 'MED1', 'name' => 'Klinik']);
        $this->decree(attributes: ['decree_number' => 'SK/LAIN'], course: $otherCourse);

        $this->actingAs(User::factory()->create(['role' => UserRole::Admin, 'faculty_id' => $this->program->faculty_id]));

        $page = Livewire::test(ListTeachingDecrees::class)->assertActionVisible('export');

        $query = $page->instance()->getFilteredSortedTableQuery();
        $csv = $this->csv($query);

        $this->assertStringContainsString('SK/SENDIRI', $csv);
        $this->assertStringNotContainsString('SK/LAIN', $csv);
        $this->assertNotNull($own->fresh());
    }

    public function test_export_action_downloads_a_file(): void
    {
        $this->decree();

        $this->actingAs($this->superAdmin());

        Livewire::test(ListTeachingDecrees::class)
            ->callAction('export')
            ->assertFileDownloaded('sipma-sk-mengajar-'.Carbon::now()->format('Ymd').'.csv');
    }
}
