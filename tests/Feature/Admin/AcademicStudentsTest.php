<?php

namespace Tests\Feature\Admin;

use App\Enums\AccountProvision;
use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\StudentStatus;
use App\Enums\UserRole;
use App\Enums\VisaProcessStatus;
use App\Filament\Admin\Resources\AcademicStudentResource;
use App\Filament\Admin\Resources\AcademicStudentResource\Pages\ListAcademicStudents;
use App\Filament\Admin\Resources\AcademicStudentResource\Pages\ViewAcademicStudent;
use App\Filament\Admin\Resources\StudentResource;
use App\Filament\Admin\Resources\StudentResource\Pages\ListApplicants;
use App\Filament\Admin\Widgets\StatCards;
use App\Models\Agent;
use App\Models\Document;
use App\Models\Faculty;
use App\Models\Student;
use App\Models\User;
use App\Notifications\StudentAccountInvitation;
use App\Notifications\StudentEnrolled;
use App\Support\ActivityDescriber;
use Filament\Facades\Filament;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\Feature\Workflow\WorkflowTestCase;

/**
 * TP-1 F1: daftar Mahasiswa Asing (LOA terbit · Aktif · Selesai), terpisah dari Pendaftar.
 */
class AcademicStudentsTest extends WorkflowTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    /**
     * @return array<string, Student>
     */
    private function oneOfEach(): array
    {
        $program = $this->program();

        return collect(StudentStatus::cases())
            ->mapWithKeys(fn (StudentStatus $status): array => [$status->value => $this->student($program, $status)])
            ->all();
    }

    public function test_list_holds_accepted_students_with_post_loa_tabs(): void
    {
        $students = $this->oneOfEach();
        $accepted = collect(StudentStatus::acceptedStatuses())->map(fn (StudentStatus $s): Student => $students[$s->value]);
        $admissions = collect($students)->reject(fn (Student $s): bool => $s->status->isAccepted());

        $this->actingAs($this->superAdmin());

        Livewire::test(ListAcademicStudents::class)
            ->assertCanSeeTableRecords($accepted)
            ->assertCanNotSeeTableRecords($admissions)
            ->set('activeTab', StudentStatus::LoaIssued->value)
            ->assertCanSeeTableRecords([$students['loa_issued']])
            ->assertCanNotSeeTableRecords([$students['enrolled'], $students['completed']])
            ->set('activeTab', StudentStatus::Enrolled->value)
            ->assertCanSeeTableRecords([$students['enrolled']])
            ->assertCanNotSeeTableRecords([$students['loa_issued'], $students['completed']]);

        $this->get(AcademicStudentResource::getUrl())
            ->assertOk()
            ->assertSee(__('admin.groups.academic'))
            ->assertSee(__('admin.academic_student.tabs.ready'))
            ->assertSee(StudentStatus::Enrolled->getLabel())
            ->assertSee(StudentStatus::Completed->getLabel());

        $this->assertSame('1', AcademicStudentResource::getNavigationBadge(), 'one student waits to be activated');
    }

    /**
     * Test berjalan di SQLite yang longgar; MySQL (only_full_group_by) menolak kolom lain bersama GROUP BY.
     */
    public function test_tab_counts_group_only_by_status(): void
    {
        $this->oneOfEach();

        $this->actingAs($this->superAdmin());

        DB::enableQueryLog();

        Livewire::test(ListAcademicStudents::class)->assertOk();

        $grouped = collect(DB::getQueryLog())->pluck('query')->filter(fn (string $sql): bool => str_contains($sql, 'group by'));

        $this->assertNotEmpty($grouped);
        $grouped->each(fn (string $sql) => $this->assertStringNotContainsString('"students".*', $sql));
        $grouped->each(fn (string $sql) => $this->assertStringNotContainsString('courses_taken', $sql));
    }

    public function test_applicants_list_stops_at_loa_issued(): void
    {
        $students = $this->oneOfEach();
        $this->actingAs($this->superAdmin());

        Livewire::test(ListApplicants::class)
            ->assertCanSeeTableRecords([$students['loa_issued'], $students['draft']])
            ->assertCanNotSeeTableRecords([$students['enrolled'], $students['completed']])
            ->assertSee(__('admin.applicant.tabs.all'));

        $tabs = Livewire::test(ListApplicants::class)->instance()->getTabs();
        $this->assertSame(count(StudentStatus::cases()) - 2, $tabs['all']->getBadge());

        // Detail pendaftarannya tetap bisa dibuka
        $this->get(StudentResource::getUrl('view', ['record' => $students['enrolled']]))->assertOk();
    }

    public function test_faculty_admin_sees_only_own_faculty_and_no_faculty_filter(): void
    {
        $own = $this->student($this->program(), StudentStatus::Enrolled);
        $otherFaculty = Faculty::create(['code' => 'FT', 'name' => 'Fakultas Teknik']);
        $other = $this->student($this->program(), StudentStatus::Enrolled);
        $other->program->update(['faculty_id' => $otherFaculty->id]);

        $admin = User::factory()->create(['role' => UserRole::Admin, 'faculty_id' => $own->program->faculty_id]);
        $this->actingAs($admin);

        $this->assertTrue(AcademicStudentResource::canViewAny());
        Livewire::test(ListAcademicStudents::class)
            ->assertCanSeeTableRecords([$own])
            ->assertCanNotSeeTableRecords([$other])
            ->assertTableFilterHidden('faculty')
            ->assertTableFilterVisible('program_id');

        $this->actingAs($this->superAdmin());
        Livewire::test(ListAcademicStudents::class)
            ->assertTableFilterVisible('faculty')
            ->filterTable('faculty', $otherFaculty->id)
            ->assertCanSeeTableRecords([$other])
            ->assertCanNotSeeTableRecords([$own]);
    }

    public function test_portal_account_column_shows_who_cannot_sign_in_yet(): void
    {
        $withAccount = $this->student(status: StudentStatus::Enrolled);
        $pending = $this->student(status: StudentStatus::LoaIssued);
        $pending->update(['user_id' => null]);
        $failed = $this->student(status: StudentStatus::Enrolled);
        $failed->update(['user_id' => null]);

        $this->actingAs($this->superAdmin());

        Livewire::test(ListAcademicStudents::class)
            ->assertTableColumnStateSet('account', __('admin.academic_student.account.yes'), $withAccount)
            ->assertTableColumnStateSet('account', __('admin.academic_student.account.no'), $pending)
            ->assertTableColumnStateSet('account', __('admin.academic_student.account.no'), $failed);
    }

    public function test_loa_card_counts_every_accepted_student_and_opens_this_list(): void
    {
        $this->oneOfEach();
        $this->actingAs($this->superAdmin());

        Livewire::test(StatCards::class)
            ->assertViewHas('cards', fn (array $cards): bool => collect($cards)->contains(fn (array $card): bool => $card['label'] === __('admin.dashboard.loa_issued')
                && $card['value'] === 3
                && $card['url'] === AcademicStudentResource::getUrl('index')));
    }

    public function test_other_roles_stay_out(): void
    {
        $this->student(status: StudentStatus::Enrolled);

        $this->actingAs($this->agent()->user)->get(AcademicStudentResource::getUrl())->assertForbidden();
        $this->actingAs($this->studentUser())->get(AcademicStudentResource::getUrl())->assertForbidden();
    }

    public function test_detail_page_summarises_the_post_loa_stage(): void
    {
        $student = $this->student(status: StudentStatus::Enrolled);
        $student->update(['enrolled_at' => '2026-11-02 08:00:00', 'enrollment_month' => 11, 'enrollment_year' => 2026]);
        $this->actingAs($this->superAdmin());

        Livewire::test(ListAcademicStudents::class)
            ->assertTableActionHasUrl('view', AcademicStudentResource::getUrl('view', ['record' => $student]), $student);

        $this->get(AcademicStudentResource::getUrl('view', ['record' => $student]))
            ->assertOk()
            ->assertSee($student->full_name)
            ->assertSee(StudentStatus::Enrolled->getLabel())
            ->assertSee($student->program->name)
            ->assertSee('2 November 2026')
            ->assertSee(__('admin.academic_student.account.yes'))
            ->assertSee(__('admin.academic_student.sections.documents'))
            ->assertSee(__('admin.academic_student.sections.payments'))
            ->assertSee(__('admin.academic_student.tabs.visa'))
            ->assertSee(__('admin.academic_student.visa.none'))
            ->assertSee(__('admin.academic_student.actions.open_application'));

        // Pendaftaran yang belum menerima LOA tidak punya detail di sini
        $draft = $this->student();
        $this->get(AcademicStudentResource::getUrl('view', ['record' => $draft]))->assertNotFound();
    }

    public function test_detail_warns_when_the_student_cannot_sign_in(): void
    {
        $ready = $this->student(status: StudentStatus::LoaIssued);
        $noAccount = $this->student(status: StudentStatus::Enrolled);
        $noAccount->update(['user_id' => null]);
        $this->actingAs($this->superAdmin());

        $this->get(AcademicStudentResource::getUrl('view', ['record' => $ready]))
            ->assertSee(__('admin.academic_student.notice.ready'));
        $this->get(AcademicStudentResource::getUrl('view', ['record' => $noAccount]))
            ->assertSee(__('admin.academic_student.notice.no_account'));
    }

    public function test_kui_updates_the_visa_and_it_shows_in_the_history(): void
    {
        $student = $this->student(status: StudentStatus::Enrolled);
        $admin = $this->superAdmin();
        $this->actingAs($admin);

        Livewire::test(ViewAcademicStudent::class, ['record' => $student->getRouteKey()])
            ->assertActionVisible('editVisa')
            ->callAction('editVisa', ['status' => VisaProcessStatus::InProcess->value, 'notes' => 'Submitted to immigration'])
            ->assertHasNoActionErrors()
            ->assertSee(VisaProcessStatus::InProcess->getLabel())
            ->assertSee('Submitted to immigration');

        $visa = $student->fresh()->visaStatus;
        $this->assertSame(VisaProcessStatus::InProcess, $visa->status);
        $this->assertTrue($visa->updater->is($admin));

        Livewire::test(ViewAcademicStudent::class, ['record' => $student->getRouteKey()])
            ->callAction('editVisa', ['status' => VisaProcessStatus::Approved->value, 'notes' => ''])
            ->assertHasNoActionErrors();
        $this->assertSame(VisaProcessStatus::Approved, $visa->fresh()->status);
        $this->assertNull($visa->fresh()->notes);
        $this->assertSame(1, $student->visaStatus()->count());

        $history = ActivityDescriber::forStudent($student)->pluck('text')->implode(' | ');
        $this->assertStringContainsString(__('admin.dashboard.activity_visa', [
            'causer' => $admin->name, 'name' => $student->full_name, 'status' => VisaProcessStatus::Approved->getLabel(),
        ]), $history);
    }

    public function test_faculty_admin_reads_a_summary_and_cannot_change_the_visa(): void
    {
        $student = $this->student(status: StudentStatus::Enrolled);
        $student->visaStatus()->create(['status' => VisaProcessStatus::InProcess, 'notes' => 'At immigration']);
        $admin = User::factory()->create(['role' => UserRole::Admin, 'faculty_id' => $student->program->faculty_id]);
        $this->actingAs($admin);

        $this->assertTrue($admin->can('view', $student->visaStatus));
        $this->assertFalse($admin->can('update', $student->visaStatus));

        $this->get(AcademicStudentResource::getUrl('view', ['record' => $student]))
            ->assertOk()
            ->assertSee(VisaProcessStatus::InProcess->getLabel())
            ->assertSee('At immigration')
            ->assertSee(__('admin.academic_student.visa.readonly'))
            ->assertDontSee(__('admin.academic_student.sections.documents'));

        Livewire::test(ViewAcademicStudent::class, ['record' => $student->getRouteKey()])
            ->assertActionHidden('editVisa');

        // Fakultas lain: ditolak
        $other = User::factory()->create(['role' => UserRole::Admin, 'faculty_id' => Faculty::create(['code' => 'FT', 'name' => 'Fakultas Teknik'])->id]);
        $this->actingAs($other)->get(AcademicStudentResource::getUrl('view', ['record' => $student]))->assertNotFound();
    }

    public function test_applicant_detail_links_to_the_international_student_page(): void
    {
        $accepted = $this->student(status: StudentStatus::LoaIssued);
        $approved = $this->student(status: StudentStatus::Approved);
        $this->actingAs($this->superAdmin());

        Livewire::test(StudentResource\Pages\ViewApplicant::class, ['record' => $accepted->getRouteKey()])
            ->assertActionVisible('viewAcademic')
            ->assertActionHasUrl('viewAcademic', AcademicStudentResource::getUrl('view', ['record' => $accepted]));
        Livewire::test(StudentResource\Pages\ViewApplicant::class, ['record' => $approved->getRouteKey()])
            ->assertActionHidden('viewAcademic');
    }

    private function viaAgent(StudentStatus $status = StudentStatus::LoaIssued, string $email = 'nadia.r@example.test'): Student
    {
        $student = $this->student(status: $status);
        $student->user()->first()->delete();
        $student->update(['user_id' => null, 'agent_id' => Agent::query()->first()?->id ?? $this->agent()->id, 'email' => $email]);

        return $student->fresh();
    }

    public function test_kui_activates_completes_and_reopens_from_the_detail_page(): void
    {
        $student = $this->viaAgent();
        $this->actingAs($this->superAdmin());

        Livewire::test(ViewAcademicStudent::class, ['record' => $student->getRouteKey()])
            ->assertActionHidden('complete')
            ->assertActionHidden('reopen')
            ->mountAction('enroll')
            ->assertSee(__('admin.academic_student.confirm.enroll_invite', ['email' => 'nadia.r@example.test']))
            ->callMountedAction()
            ->assertNotified(__('admin.academic_student.success.enrolled', ['name' => $student->full_name]))
            ->assertActionHidden('enroll')
            ->assertActionVisible('complete');

        $student->refresh();
        $this->assertSame(StudentStatus::Enrolled, $student->status);
        Notification::assertSentTo($student->user, StudentAccountInvitation::class);

        Livewire::test(ViewAcademicStudent::class, ['record' => $student->getRouteKey()])
            ->callAction('complete')
            ->assertActionVisible('reopen');
        $this->assertSame(StudentStatus::Completed, $student->fresh()->status);

        Livewire::test(ViewAcademicStudent::class, ['record' => $student->getRouteKey()])->callAction('reopen');
        $this->assertSame(StudentStatus::Enrolled, $student->fresh()->status);
    }

    public function test_activation_warns_when_no_portal_account_could_be_made(): void
    {
        $agent = $this->agent();
        $student = $this->viaAgent(email: $agent->user->email);
        $this->actingAs($this->superAdmin());

        Livewire::test(ViewAcademicStudent::class, ['record' => $student->getRouteKey()])
            ->callAction('enroll')
            ->assertNotified(
                FilamentNotification::make()
                    ->title(__('admin.academic_student.success.enrolled', ['name' => $student->full_name]))
                    ->body(AccountProvision::EmailTaken->getLabel())
                    ->warning()
            );

        $this->assertSame(StudentStatus::Enrolled, $student->fresh()->status);
    }

    public function test_faculty_admin_activates_and_completes_but_cannot_reopen(): void
    {
        $student = $this->student(status: StudentStatus::LoaIssued);
        $admin = User::factory()->create(['role' => UserRole::Admin, 'faculty_id' => $student->program->faculty_id]);
        $this->actingAs($admin);

        Livewire::test(ViewAcademicStudent::class, ['record' => $student->getRouteKey()])->callAction('enroll');
        Livewire::test(ViewAcademicStudent::class, ['record' => $student->getRouteKey()])->callAction('complete');
        $this->assertSame(StudentStatus::Completed, $student->fresh()->status);
        $this->assertTrue($student->fresh()->enroller->is($admin));

        Livewire::test(ViewAcademicStudent::class, ['record' => $student->getRouteKey()])->assertActionHidden('reopen');
    }

    public function test_list_activates_one_row_or_many_and_skips_the_rest(): void
    {
        $program = $this->program();
        $ready = collect(range(1, 3))->map(fn (): Student => $this->student($program, StudentStatus::LoaIssued));
        $active = $this->student($program, StudentStatus::Enrolled);
        $this->actingAs($this->superAdmin());

        Livewire::test(ListAcademicStudents::class)
            ->assertTableActionVisible('enroll', $ready[0])
            ->assertTableActionHidden('enroll', $active)
            ->callTableAction('enroll', $ready[0]);
        $this->assertSame(StudentStatus::Enrolled, $ready[0]->fresh()->status);
        session()->forget('filament.notifications');

        Livewire::test(ListAcademicStudents::class)
            ->callTableBulkAction('enrollSelected', [...$ready, $active])
            ->assertNotified(
                FilamentNotification::make()
                    ->title(__('admin.academic_student.success.bulk', ['count' => 2]))
                    ->body(__('admin.academic_student.success.bulk_skipped', ['count' => 2]))
                    ->warning()
            );

        foreach ($ready as $student) {
            $this->assertSame(StudentStatus::Enrolled, $student->fresh()->status);
        }
        Notification::assertSentToTimes($ready[1]->user, StudentEnrolled::class, 1);
    }

    private function withPhoto(Student $student, DocumentStatus $status = DocumentStatus::Approved): Document
    {
        return $student->documents()->create([
            'type' => DocumentType::Photo,
            'file_path' => UploadedFile::fake()->image('photo.jpg', 600, 800)->store('photos', 'local'),
            'original_name' => 'photo.jpg',
            'file_size' => 1024,
            'mime_type' => 'image/jpeg',
            'status' => $status,
        ]);
    }

    public function test_documents_can_be_previewed_and_downloaded_from_the_detail_page(): void
    {
        $student = $this->student(status: StudentStatus::Enrolled);
        $document = $this->withPhoto($student);

        $this->actingAs($this->superAdmin());

        $this->get(AcademicStudentResource::getUrl('view', ['record' => $student]))
            ->assertOk()
            ->assertSee(__('admin.applicant.actions.preview'))
            ->assertSee(route('files.document', ['document' => $document, 'download' => 1]), false);

        Livewire::test(ViewAcademicStudent::class, ['record' => $student->getRouteKey()])
            ->mountAction('previewDocument', ['document' => $document->getKey()])
            ->assertSee(route('files.document', $document), false);

        $this->get(route('files.document', ['document' => $document, 'download' => 1]))->assertOk()->assertDownload();
    }

    public function test_preview_only_opens_documents_of_this_student(): void
    {
        $student = $this->student(status: StudentStatus::Enrolled);
        $foreign = $this->withPhoto($this->student(status: StudentStatus::Enrolled));

        $this->actingAs($this->superAdmin());

        $this->expectException(ModelNotFoundException::class);

        Livewire::test(ViewAcademicStudent::class, ['record' => $student->getRouteKey()])
            ->mountAction('previewDocument', ['document' => $foreign->getKey()]);
    }

    public function test_faculty_admin_gets_no_document_buttons_or_files(): void
    {
        $student = $this->student(status: StudentStatus::Enrolled);
        $document = $this->withPhoto($student);

        $this->actingAs(User::factory()->create(['role' => UserRole::Admin, 'faculty_id' => $student->program->faculty_id]));

        $this->get(AcademicStudentResource::getUrl('view', ['record' => $student]))
            ->assertOk()
            ->assertDontSee(__('admin.applicant.actions.preview'))
            ->assertDontSee(route('files.document', $document), false);

        // Tombol disembunyikan bukan satu-satunya pengaman: berkasnya sendiri ditolak
        $this->get(route('files.document', $document))->assertForbidden();
    }

    public function test_photo_shows_in_the_summary_and_follows_student_access(): void
    {
        $student = $this->student(status: StudentStatus::Enrolled);
        $url = route('files.student-photo', $student);

        $this->actingAs($this->superAdmin());

        // Belum ada foto: inisial, tanpa gambar rusak
        $this->get(AcademicStudentResource::getUrl('view', ['record' => $student]))->assertOk()->assertDontSee($url, false);
        $this->get($url)->assertNotFound();

        // Foto yang belum disetujui tidak ditampilkan
        $pending = $this->withPhoto($student, DocumentStatus::Pending);
        $this->get(AcademicStudentResource::getUrl('view', ['record' => $student]))->assertDontSee($url, false);
        $pending->update(['status' => DocumentStatus::Approved]);

        $this->get(AcademicStudentResource::getUrl('view', ['record' => $student]))->assertSee($url, false);

        $response = $this->get($url)->assertOk();
        $this->assertSame('image/jpeg', $response->headers->get('Content-Type'));
        $this->assertSame([248, 331], array_slice(getimagesizefromstring((string) $response->getContent()), 0, 2));

        // Admin Fakultas pemilik boleh; fakultas lain, mahasiswa lain, dan tamu tidak
        $own = User::factory()->create(['role' => UserRole::Admin, 'faculty_id' => $student->program->faculty_id]);
        $this->actingAs($own);
        $this->get(AcademicStudentResource::getUrl('view', ['record' => $student]))->assertSee($url, false);
        $this->get($url)->assertOk();

        $other = User::factory()->create(['role' => UserRole::Admin, 'faculty_id' => Faculty::create(['code' => 'FK', 'name' => 'Fakultas Kedokteran'])->id]);
        $this->actingAs($other);
        $this->get($url)->assertForbidden();

        $this->actingAs($this->studentUser());
        $this->get($url)->assertForbidden();

        auth()->logout();
        $this->get($url)->assertRedirect();
    }

    public function test_list_completes_one_row_or_many_and_skips_the_rest(): void
    {
        $program = $this->program();
        $one = $this->student($program, StudentStatus::Enrolled);
        $two = $this->student($program, StudentStatus::Enrolled);
        $three = $this->student($program, StudentStatus::Enrolled);
        $notYet = $this->student($program, StudentStatus::LoaIssued);

        $this->actingAs($this->superAdmin());

        Livewire::test(ListAcademicStudents::class)->callTableAction('complete', $one);
        $this->assertSame(StudentStatus::Completed, $one->fresh()->status);
        $this->assertNotNull($one->fresh()->completed_at);

        // Notifikasi aksi satu baris tidak ikut dibandingkan di bawah
        session()->forget('filament.notifications');

        Livewire::test(ListAcademicStudents::class)
            ->assertTableActionHidden('complete', $notYet)
            ->callTableBulkAction('completeSelected', [$two, $three, $notYet])
            ->assertHasNoTableBulkActionErrors();

        $this->assertSame(StudentStatus::Completed, $two->fresh()->status);
        $this->assertSame(StudentStatus::Completed, $three->fresh()->status);
        $this->assertSame(StudentStatus::LoaIssued, $notYet->fresh()->status);

        FilamentNotification::assertNotified(
            FilamentNotification::make()
                ->warning()
                ->title(trans_choice('admin.academic_student.success.bulk_completed', 2, ['count' => 2]))
                ->body(__('admin.academic_student.success.bulk_skipped', ['count' => 1])),
        );
    }

    public function test_faculty_admin_bulk_complete_skips_other_faculties(): void
    {
        $own = $this->student(status: StudentStatus::Enrolled);
        $admin = User::factory()->create(['role' => UserRole::Admin, 'faculty_id' => $own->program->faculty_id]);
        $this->actingAs($admin);

        $foreign = $this->student(status: StudentStatus::Enrolled);
        $foreign->program->update(['faculty_id' => Faculty::create(['code' => 'FK', 'name' => 'Fakultas Kedokteran'])->id]);

        AcademicStudentResource::completeMany(Student::query()->whereKey([$own->id, $foreign->id])->with('program')->get());

        $this->assertSame(StudentStatus::Completed, $own->fresh()->status);
        $this->assertSame(StudentStatus::Enrolled, $foreign->fresh()->status);
    }

    public function test_bulk_buttons_and_their_guide_follow_the_active_tab(): void
    {
        $this->actingAs($this->superAdmin());

        $guides = fn (): array => [__('admin.academic_student.guide.enroll'), __('admin.academic_student.guide.issue'), __('admin.academic_student.guide.complete')];
        [$enroll, $issue, $complete] = $guides();

        $page = Livewire::test(ListAcademicStudents::class)
            ->assertSee($enroll)->assertSee($issue)->assertSee($complete)
            ->assertTableBulkActionVisible('enrollSelected')
            ->assertTableBulkActionVisible('issueCards')
            ->assertTableBulkActionVisible('completeSelected');

        $page->set('activeTab', StudentStatus::LoaIssued->value)
            ->assertSee($enroll)->assertDontSee($issue)->assertDontSee($complete)
            ->assertTableBulkActionVisible('enrollSelected')
            ->assertTableBulkActionHidden('issueCards')
            ->assertTableBulkActionHidden('completeSelected');

        $page->set('activeTab', StudentStatus::Enrolled->value)
            ->assertDontSee($enroll)->assertSee($issue)->assertSee($complete)
            ->assertTableBulkActionHidden('enrollSelected')
            ->assertTableBulkActionVisible('issueCards')
            ->assertTableBulkActionVisible('completeSelected');

        $page->set('activeTab', StudentStatus::Completed->value)
            ->assertDontSee($enroll)->assertDontSee($issue)->assertDontSee($complete)
            ->assertDontSee(__('admin.academic_student.guide.intro'));
    }

    public function test_faculty_admin_guide_leaves_out_issuing_cards(): void
    {
        $student = $this->student(status: StudentStatus::Enrolled);
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin, 'faculty_id' => $student->program->faculty_id]));

        Livewire::test(ListAcademicStudents::class)
            ->assertSee(__('admin.academic_student.guide.complete'))
            ->assertDontSee(__('admin.academic_student.guide.issue'))
            ->assertTableBulkActionHidden('issueCards');
    }

    public function test_bulk_complete_warns_about_ungraded_courses(): void
    {
        $student = $this->student(status: StudentStatus::Enrolled);
        $course = $student->program->courses()->create(['code' => 'MK1', 'name' => 'MK', 'is_active' => true]);
        $student->studentCourses()->create(['course_id' => $course->id]);

        $this->actingAs($this->superAdmin());

        Livewire::test(ListAcademicStudents::class)
            ->mountTableBulkAction('completeSelected', [$student])
            ->assertSee(__('admin.academic_student.guide.complete'))
            ->assertSee(trans_choice('admin.academic_student.confirm.complete_bulk_ungraded', 1, ['count' => 1]));
    }

    public function test_dashboard_counts_active_students_this_month(): void
    {
        $this->travelTo('2026-11-15 10:00:00');
        $program = $this->program();
        $this->student($program, StudentStatus::Enrolled)->update(['enrolled_at' => '2026-11-03 09:00:00']);
        $this->student($program, StudentStatus::Enrolled)->update(['enrolled_at' => '2026-09-01 09:00:00']);
        $this->student($program, StudentStatus::Completed);
        $this->actingAs($this->superAdmin());

        Livewire::test(StatCards::class)
            ->assertViewHas('cards', fn (array $cards): bool => collect($cards)->contains(fn (array $card): bool => $card['label'] === __('admin.dashboard.active_students')
                && $card['value'] === 2
                && $card['badge'] === '+1'
                && $card['url'] === AcademicStudentResource::getUrl('index', ['activeTab' => 'enrolled'])))
            ->assertSeeHtml('is-five');
    }
}
