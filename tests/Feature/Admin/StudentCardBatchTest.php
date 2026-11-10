<?php

namespace Tests\Feature\Admin;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\StudentStatus;
use App\Enums\UserRole;
use App\Filament\Admin\Resources\AcademicStudentResource\Pages\ListAcademicStudents;
use App\Filament\Admin\Resources\StudentCardResource\Pages\ListStudentCards;
use App\Filament\Support\StudentCardBatch;
use App\Models\Faculty;
use App\Models\Program;
use App\Models\Student;
use App\Models\StudentCard;
use App\Models\User;
use App\Support\EnrollmentPeriod;
use App\Support\StudentCardArchive;
use App\Workflow\StudentCardWorkflow;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\Feature\Workflow\WorkflowTestCase;
use ZipArchive;

/**
 * TP-2 F4: terbit massal KTM per periode atau centang, dan unduhan ZIP.
 */
class StudentCardBatchTest extends WorkflowTestCase
{
    private const PERIOD = '2026-09';

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Carbon::setTestNow('2026-11-04 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function durationProgram(?Faculty $faculty = null, ?int $months = 6): Program
    {
        $program = $this->program();
        $program->update(['duration_months' => $months, 'faculty_id' => ($faculty ?? $program->faculty)->getKey()]);

        return $program->fresh();
    }

    private function enrolled(?Program $program = null, string $period = self::PERIOD, bool $photo = true, StudentStatus $status = StudentStatus::Enrolled, string $name = 'Ana Sato'): Student
    {
        [$year, $month] = EnrollmentPeriod::parse($period);

        $student = $this->student($program ?? $this->durationProgram(), $status);
        $student->update(['full_name' => $name, 'enrollment_year' => $year, 'enrollment_month' => $month]);

        if ($photo) {
            $this->approvePhoto($student);
        }

        return $student->fresh();
    }

    private function approvePhoto(Student $student): void
    {
        $student->documents()->create([
            'type' => DocumentType::Photo,
            'file_path' => 'photo.png',
            'original_name' => 'photo.png',
            'file_size' => 1024,
            'mime_type' => 'image/png',
            'status' => DocumentStatus::Approved,
        ]);
    }

    private function facultyAdmin(Faculty $faculty): User
    {
        return User::factory()->create(['role' => UserRole::Admin, 'faculty_id' => $faculty->id]);
    }

    /**
     * @return list<string> nama berkas di dalam ZIP
     */
    private function zipEntries(TestResponse $response): array
    {
        $file = $response->baseResponse;

        $this->assertInstanceOf(BinaryFileResponse::class, $file);

        $path = $file->getFile()->getPathname();
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path));

        $entries = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entries[] = $zip->getNameIndex($i);

            [, , $type] = getimagesizefromstring((string) $zip->getFromIndex($i));
            $this->assertSame(IMAGETYPE_JPEG, $type);
        }

        $zip->close();
        @unlink($path);

        return $entries;
    }

    public function test_period_issue_covers_only_that_period_and_skips_the_rest(): void
    {
        $program = $this->durationProgram();
        $ready = $this->enrolled($program);
        $noPhoto = $this->enrolled($program, photo: false, name: 'Tanpa Foto');
        $otherPeriod = $this->enrolled($program, period: '2026-08');

        $this->actingAs($this->superAdmin());

        Livewire::test(ListStudentCards::class)
            ->callAction('issueByPeriod', ['period' => self::PERIOD])
            ->assertHasNoActionErrors();

        $this->assertSame(1, $ready->studentCards()->count());
        $this->assertSame(0, $noPhoto->studentCards()->count());
        $this->assertSame(0, $otherPeriod->studentCards()->count());

        // Masuk September, 6 bulan → akhir Februari
        $this->assertSame('2027-02-28', $ready->studentCards()->first()->valid_until->toDateString());

        Notification::assertNotified(
            Notification::make()
                ->warning()
                ->title(trans_choice('admin.student_card.batch.issued', 1, ['count' => 1]))
                ->body(trans_choice('admin.student_card.batch.issued_skipped', 1, ['count' => 1, 'names' => 'Tanpa Foto'])),
        );
    }

    public function test_running_a_period_again_only_issues_what_is_still_missing(): void
    {
        $program = $this->durationProgram();
        $first = $this->enrolled($program);
        $later = $this->enrolled($program, photo: false);

        $this->actingAs($this->superAdmin());

        Livewire::test(ListStudentCards::class)->callAction('issueByPeriod', ['period' => self::PERIOD]);

        $number = $first->studentCards()->value('card_number');
        $this->approvePhoto($later);

        Livewire::test(ListStudentCards::class)->callAction('issueByPeriod', ['period' => self::PERIOD]);

        $this->assertSame(1, $first->studentCards()->count());
        $this->assertSame($number, $first->studentCards()->value('card_number'));
        $this->assertSame(1, $later->studentCards()->count());
        $this->assertSame(2, StudentCard::query()->distinct()->count('card_number'));
    }

    public function test_fallback_date_only_applies_to_students_without_a_calculated_date(): void
    {
        $withDuration = $this->enrolled($this->durationProgram());
        $withoutDuration = $this->enrolled($this->durationProgram(months: null));
        // Masuk September selama 1 bulan: tanggal hitungannya sudah lewat
        $overdue = $this->enrolled($this->durationProgram(months: 1));

        $this->actingAs($this->superAdmin());

        // Tanpa tanggal cadangan: yang tidak bisa dihitung dilewati
        Livewire::test(ListStudentCards::class)->callAction('issueByPeriod', ['period' => self::PERIOD]);

        $this->assertSame(1, $withDuration->studentCards()->count());
        $this->assertSame(0, $withoutDuration->studentCards()->count());
        $this->assertSame(0, $overdue->studentCards()->count());

        Livewire::test(ListStudentCards::class)->callAction('issueByPeriod', ['period' => self::PERIOD, 'valid_until' => '2027-06-30']);

        $this->assertSame('2027-06-30', $withoutDuration->studentCards()->first()->valid_until->toDateString());
        $this->assertSame('2027-06-30', $overdue->studentCards()->first()->valid_until->toDateString());
        $this->assertSame('2027-02-28', $withDuration->studentCards()->first()->valid_until->toDateString());
    }

    public function test_selected_students_get_cards_and_ineligible_ones_are_skipped(): void
    {
        $program = $this->durationProgram();
        $ready = $this->enrolled($program);
        $notYet = $this->enrolled($program, status: StudentStatus::LoaIssued);
        $hasCard = $this->enrolled($program);
        $existing = app(StudentCardWorkflow::class)->issue($hasCard, now()->addYear());

        $this->actingAs($this->superAdmin());

        Livewire::test(ListAcademicStudents::class)
            ->callTableBulkAction('issueCards', [$ready, $notYet, $hasCard])
            ->assertHasNoTableBulkActionErrors();

        $this->assertSame(1, $ready->studentCards()->count());
        $this->assertSame(0, $notYet->studentCards()->count());
        $this->assertSame([$existing->getKey()], $hasCard->studentCards()->pluck('id')->all());
    }

    public function test_plan_names_who_is_skipped_and_why_without_issuing(): void
    {
        $program = $this->durationProgram();
        $ready = $this->enrolled($program);
        $noPhoto = $this->enrolled($program, photo: false, name: 'Tanpa Foto');

        $plan = StudentCardBatch::plan(Student::query()->whereKey([$ready->id, $noPhoto->id])->get());

        $this->assertSame(1, $plan['ready']);
        $this->assertSame([['name' => 'Tanpa Foto', 'reason' => __('workflow.errors.card_photo_missing')]], $plan['skipped']);
        $this->assertSame(0, StudentCard::query()->count());
    }

    public function test_faculty_admin_downloads_own_faculty_but_cannot_issue(): void
    {
        $own = $this->enrolled();
        $other = $this->enrolled($this->durationProgram(Faculty::create(['code' => 'FK', 'name' => 'Fakultas Kedokteran'])), period: '2026-08');

        foreach ([$own, $other] as $student) {
            app(StudentCardWorkflow::class)->issue($student, now()->addYear());
        }

        $this->actingAs($this->facultyAdmin($own->program->faculty));

        $component = Livewire::test(ListStudentCards::class)
            ->assertActionHidden('issueByPeriod')
            ->assertActionHidden('issue')
            ->assertActionVisible('downloadByPeriod');

        $this->assertSame([self::PERIOD], array_keys(EnrollmentPeriod::options(Student::query()->visibleTo(auth()->user())->whereHas('activeCard'))));

        // Periode fakultas lain tidak menghasilkan apa-apa
        $component->callAction('downloadByPeriod', ['period' => '2026-08']);
        $this->assertEmpty(session('student_card_archives', []));

        Livewire::test(ListAcademicStudents::class)->assertTableBulkActionHidden('issueCards');
    }

    public function test_period_zip_holds_only_active_cards_of_that_period(): void
    {
        $program = $this->durationProgram();
        $active = app(StudentCardWorkflow::class)->issue($this->enrolled($program, name: 'Ana Sato'));
        $revoked = app(StudentCardWorkflow::class)->issue($this->enrolled($program, name: 'Dicabut Saja'));
        app(StudentCardWorkflow::class)->revoke($revoked, 'Data keliru');
        app(StudentCardWorkflow::class)->issue($this->enrolled($program, period: '2026-08', name: 'Beda Periode'));

        $this->actingAs($this->superAdmin());

        Livewire::test(ListStudentCards::class)->callAction('downloadByPeriod', ['period' => self::PERIOD]);

        $token = array_key_first(session('student_card_archives'));
        $response = $this->get(route('files.student-card-archive', ['token' => $token]))->assertOk();

        $this->assertStringContainsString('KTM-2026-09.zip', (string) $response->headers->get('Content-Disposition'));
        $this->assertSame(["{$active->card_number}_ana-sato.jpg"], $this->zipEntries($response));

        // Tautannya sekali pakai
        $this->get(route('files.student-card-archive', ['token' => $token]))->assertNotFound();
    }

    public function test_selected_zip_filters_out_cards_outside_the_users_access(): void
    {
        $own = app(StudentCardWorkflow::class)->issue($this->enrolled());
        $other = app(StudentCardWorkflow::class)->issue($this->enrolled($this->durationProgram(Faculty::create(['code' => 'FK', 'name' => 'Fakultas Kedokteran']))));

        $admin = $this->facultyAdmin($own->student->program->faculty);
        $this->actingAs($admin);

        Livewire::test(ListStudentCards::class)
            ->assertTableBulkActionVisible('downloadZip')
            ->callTableBulkAction('downloadZip', [$own]);

        $this->assertNotEmpty(session('student_card_archives'));

        // Tautan berisi kartu fakultas lain (session dimanipulasi): kartu itu tetap tidak ikut
        $mixed = StudentCardArchive::prepare([$own->getKey(), $other->getKey()], 'x.zip');
        $this->assertSame(["{$own->card_number}_ana-sato.jpg"], $this->zipEntries($this->get($mixed)->assertOk()));

        $foreign = StudentCardArchive::prepare([$other->getKey()], 'x.zip');
        $this->get($foreign)->assertForbidden();
    }

    public function test_zip_link_is_closed_to_guests_and_students(): void
    {
        $card = app(StudentCardWorkflow::class)->issue($this->enrolled());

        $this->actingAs($this->superAdmin());
        $link = StudentCardArchive::prepare([$card->getKey()], 'x.zip');

        auth()->logout();
        $this->get($link)->assertRedirect();

        // Mahasiswa hanya mendapat kartunya sendiri lewat pemeriksaan per kartu
        $this->actingAs($this->studentUser());
        $this->get(StudentCardArchive::prepare([$card->getKey()], 'x.zip'))->assertForbidden();
    }

    public function test_zip_flushes_large_batches_and_keeps_every_card(): void
    {
        $program = $this->durationProgram();
        $card = app(StudentCardWorkflow::class)->issue($this->enrolled($program));

        $cards = collect(range(1, 55))->map(function (int $i) use ($card): StudentCard {
            $copy = $card->replicate();
            $copy->card_number = "KTMA-{$i}";
            $copy->setRelation('student', $card->student);

            return $copy;
        });

        $path = StudentCardArchive::build($cards);
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path));
        $this->assertSame(55, $zip->numFiles);
        $zip->close();
        @unlink($path);
    }

    public function test_page_explains_each_button_for_the_role(): void
    {
        $this->actingAs($this->superAdmin());

        $guide = fn (string $key): string => __("admin.student_card.guide.{$key}");

        Livewire::test(ListStudentCards::class)
            ->assertSee(__('admin.notice.guide_title'))
            ->assertSee($guide('issue_period'))
            ->assertSee($guide('issue'))
            ->assertSee($guide('download_zip'))
            ->assertSee($guide('reissue'))
            ->assertSee($guide('revoke'));

        $student = $this->enrolled();
        $this->actingAs($this->facultyAdmin($student->program->faculty));

        Livewire::test(ListStudentCards::class)
            ->assertSee($guide('download_zip'))
            ->assertSee($guide('download'))
            ->assertDontSee($guide('issue_period'))
            ->assertDontSee($guide('reissue'))
            ->assertDontSee($guide('revoke'));
    }

    public function test_modals_say_what_the_action_does(): void
    {
        $student = $this->enrolled();
        $card = app(StudentCardWorkflow::class)->issue($student);

        $this->actingAs($this->superAdmin());

        Livewire::test(ListStudentCards::class)
            ->mountAction('issueByPeriod')
            ->assertSee(__('admin.student_card.guide.issue_period'));

        Livewire::test(ListStudentCards::class)
            ->mountAction('downloadByPeriod')
            ->assertSee(__('admin.student_card.batch.download_body'));

        Livewire::test(ListStudentCards::class)
            ->mountAction('issue')
            ->assertSee(__('admin.student_card.guide.issue'));

        Livewire::test(ListStudentCards::class)
            ->mountTableAction('reissue', $card)
            ->assertSee(__('admin.student_card.guide.reissue'));

        Livewire::test(ListStudentCards::class)
            ->mountTableAction('revoke', $card)
            ->assertSee(__('admin.student_card.guide.revoke'));
    }

    public function test_invalid_period_matches_nobody(): void
    {
        $this->enrolled();

        $this->assertNull(EnrollmentPeriod::parse('2026-13'));
        $this->assertNull(EnrollmentPeriod::parse("2026-09' or 1=1"));
        $this->assertSame(0, Student::query()->inEnrollmentPeriod('bukan-periode')->count());
        $this->assertSame(1, Student::query()->inEnrollmentPeriod(self::PERIOD)->count());
    }

    public function test_period_options_are_translated_and_counted(): void
    {
        $program = $this->durationProgram();
        $this->enrolled($program);
        $this->enrolled($program);

        app()->setLocale('en');

        $this->assertSame(
            [self::PERIOD => 'September 2026 · 2 without a card'],
            EnrollmentPeriod::options(Student::query(), 'admin.student_card.batch.period_issue'),
        );
    }
}
