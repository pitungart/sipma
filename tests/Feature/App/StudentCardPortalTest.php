<?php

namespace Tests\Feature\App;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\StudentCardStatus;
use App\Enums\StudentStatus;
use App\Enums\UserRole;
use App\Filament\App\Pages\Dashboard;
use App\Filament\App\Pages\StudentCard as StudentCardPage;
use App\Models\Faculty;
use App\Models\Student;
use App\Models\StudentCard;
use App\Models\User;
use App\Notifications\StudentCardIssued;
use App\Notifications\StudentCardRevoked;
use App\Support\StudentCardDocument;
use App\Workflow\StudentCardWorkflow;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Feature\Workflow\WorkflowTestCase;

/**
 * TP-2 F2: PDF KTM, hak unduh, dan halaman KTM di portal mahasiswa.
 */
class StudentCardPortalTest extends WorkflowTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('app'));
    }

    private function enrolled(StudentStatus $status = StudentStatus::Enrolled, ?UploadedFile $photo = null): Student
    {
        $student = $this->student(status: $status);
        $photo ??= UploadedFile::fake()->image('photo.jpg', 600, 800);

        $student->documents()->create([
            'type' => DocumentType::Photo,
            'file_path' => $photo->store('photos', 'local'),
            'original_name' => 'photo.jpg',
            'file_size' => 1024,
            'mime_type' => 'image/jpeg',
            'status' => DocumentStatus::Approved,
        ]);

        return $student;
    }

    private function issued(?Student $student = null): StudentCard
    {
        return app(StudentCardWorkflow::class)->issue($student ?? $this->enrolled(), now()->addYear());
    }

    private function url(StudentCard $card, bool $download = true): string
    {
        return route('files.student-card', ['card' => $card, 'download' => $download ? 1 : null]);
    }

    public function test_owner_downloads_a_real_single_page_pdf(): void
    {
        $card = $this->issued();

        $response = $this->actingAs($card->student->user)->get($this->url($card))->assertOk();

        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('KTM-KTMA-'.now()->year.'-0001.pdf', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF', (string) $response->getContent());
    }

    public function test_print_opens_the_pdf_inline(): void
    {
        $card = $this->issued();

        $response = $this->actingAs($card->student->user)->get($this->url($card, download: false))->assertOk();

        $this->assertStringContainsString('inline', (string) $response->headers->get('Content-Disposition'));
    }

    public function test_extreme_text_still_fits_one_page(): void
    {
        $student = $this->enrolled();
        $student->update(['full_name' => 'Şahin Ñandú Müller-Đặng '.str_repeat('Abdullah Muhammad ', 8)]);
        $student->program->update(['name' => str_repeat('Indonesian Language for Foreign Speakers ', 5)]);
        $student->program->faculty->update(['name' => str_repeat('Fakultas Matematika dan Ilmu Pengetahuan Alam ', 4)]);

        $pdf = StudentCardDocument::pdf($this->issued($student->fresh()));
        $pdf->render();

        $this->assertSame(1, $pdf->getDomPDF()->getCanvas()->get_page_count());
    }

    public function test_paper_is_the_size_of_a_card(): void
    {
        $pdf = StudentCardDocument::pdf($this->issued());
        $pdf->render();

        $canvas = $pdf->getDomPDF()->getCanvas();

        $this->assertEqualsWithDelta(85.6, $canvas->get_width() / 72 * 25.4, 0.1);
        $this->assertEqualsWithDelta(54, $canvas->get_height() / 72 * 25.4, 0.1);
    }

    public function test_access_follows_role_faculty_and_status(): void
    {
        $card = $this->issued();

        $this->actingAs($this->studentUser())->get($this->url($card))->assertForbidden();

        $agent = $this->agent();
        $card->student->update(['agent_id' => $agent->id]);
        $this->actingAs($agent->user)->get($this->url($card))->assertForbidden();

        $own = User::factory()->create(['role' => UserRole::Admin, 'faculty_id' => $card->student->program->faculty_id]);
        $this->actingAs($own)->get($this->url($card))->assertOk();

        $other = User::factory()->create(['role' => UserRole::Admin, 'faculty_id' => Faculty::create(['code' => 'FK', 'name' => 'Fakultas Kedokteran'])->id]);
        $this->actingAs($other)->get($this->url($card))->assertForbidden();

        $this->actingAs($this->superAdmin())->get($this->url($card))->assertOk();

        auth()->logout();
        $this->get($this->url($card))->assertRedirect();

        // Belum Aktif: pemilik pun ditolak
        $card->student->update(['status' => StudentStatus::LoaIssued]);
        $this->actingAs($card->student->user)->get($this->url($card->fresh()))->assertForbidden();
    }

    public function test_revoked_card_stays_with_staff_only(): void
    {
        $card = $this->issued();
        app(StudentCardWorkflow::class)->revoke($card, 'Data keliru');

        $this->actingAs($card->student->user)->get($this->url($card->fresh()))->assertForbidden();
        $this->actingAs($this->superAdmin())->get($this->url($card->fresh()))->assertOk();
    }

    public function test_expired_card_can_still_be_downloaded_by_its_owner(): void
    {
        $card = $this->issued();
        $card->update(['status' => StudentCardStatus::Expired]);

        $this->actingAs($card->student->user)->get($this->url($card->fresh()))->assertOk();
    }

    public function test_non_active_cards_carry_a_watermark(): void
    {
        $card = $this->issued();

        $this->assertStringNotContainsString('REVOKED', view('pdf.student-card', ['card' => $card])->render());

        $card->update(['status' => StudentCardStatus::Revoked]);
        $this->assertStringContainsString('DICABUT / REVOKED', view('pdf.student-card', ['card' => $card->fresh()])->render());

        $card->update(['status' => StudentCardStatus::Expired]);
        $this->assertStringContainsString('KEDALUWARSA / EXPIRED', view('pdf.student-card', ['card' => $card->fresh()])->render());
    }

    public function test_photo_is_cropped_and_a_missing_or_broken_file_falls_back_to_initials(): void
    {
        $student = $this->enrolled(photo: UploadedFile::fake()->image('wide.png', 1200, 600));

        $uri = StudentCardDocument::photo($student);
        $this->assertStringStartsWith('data:image/jpeg;base64,', $uri);

        [$width, $height] = getimagesizefromstring(base64_decode(substr($uri, strlen('data:image/jpeg;base64,'))));
        $this->assertSame([300, 400], [$width, $height]);

        // Rusak: bukan gambar
        $path = $student->documents()->value('file_path');
        Storage::disk('local')->put($path, 'not an image');
        $this->assertNull(StudentCardDocument::photo($student));

        // Hilang: PDF tetap terbentuk dengan inisial
        Storage::disk('local')->delete($path);
        $this->assertNull(StudentCardDocument::photo($student));

        $card = $this->issued($student);
        $this->assertStringContainsString('>MM</div>', view('pdf.student-card', ['card' => $card])->render());
        $this->actingAs($student->user)->get($this->url($card))->assertOk();
    }

    public function test_user_text_cannot_inject_markup_into_the_card(): void
    {
        $student = $this->enrolled();
        $student->update(['full_name' => '<img src="http://evil.test/x.png">']);

        $html = view('pdf.student-card', ['card' => $this->issued($student->fresh())])->render();

        $this->assertStringNotContainsString('<img src="http://evil.test', $html);
    }

    public function test_portal_page_is_only_for_active_or_completed_students(): void
    {
        $student = $this->enrolled(StudentStatus::LoaIssued);

        $this->actingAs($student->user);
        $this->get(StudentCardPage::getUrl(panel: 'app'))->assertForbidden();
        $this->assertFalse(StudentCardPage::shouldRegisterNavigation());

        $student->update(['status' => StudentStatus::Enrolled]);
        $this->get(StudentCardPage::getUrl(panel: 'app'))->assertOk();
        $this->assertTrue(StudentCardPage::shouldRegisterNavigation());
    }

    public function test_portal_page_shows_each_card_state(): void
    {
        $student = $this->enrolled();

        $this->actingAs($student->user);

        // Belum terbit
        Livewire::test(StudentCardPage::class)
            ->assertSee(__('student.card.pending_title'))
            ->assertDontSee(__('student.card.download'));

        // Aktif
        $card = $this->issued($student);
        Livewire::test(StudentCardPage::class)
            ->assertSee($card->card_number)
            ->assertSee($this->url($card), false)
            ->assertSee(__('student.card.download'))
            ->assertSee(__('student.card.print'));

        // Kedaluwarsa: tetap bisa diunduh, dengan keterangan
        $card->update(['status' => StudentCardStatus::Expired]);
        Livewire::test(StudentCardPage::class)
            ->assertSee(__('student.card.expired_notice'))
            ->assertSee(__('student.card.download'));

        // Dicabut: hanya keterangan dan alasan
        $card->update(['status' => StudentCardStatus::Revoked, 'revoked_at' => now(), 'revoke_reason' => 'Nomor paspor salah']);
        Livewire::test(StudentCardPage::class)
            ->assertSee('Nomor paspor salah')
            ->assertDontSee(__('student.card.download'))
            ->assertDontSee('ktm-photo');
    }

    public function test_active_card_wins_over_older_revoked_ones(): void
    {
        $old = $this->issued();
        $new = app(StudentCardWorkflow::class)->reissue($old, 'Hilang');

        $this->assertTrue($old->student->currentCard()->is($new));
    }

    public function test_dashboard_summarises_the_card(): void
    {
        $student = $this->enrolled();

        $this->actingAs($student->user);

        Livewire::test(Dashboard::class)->assertSee(__('student.card.pending_title'));

        $card = $this->issued($student);

        Livewire::test(Dashboard::class)->assertSee($card->card_number)->assertSee(__('student.card.open'));
    }

    public function test_notifications_open_the_card_page(): void
    {
        $card = $this->issued();
        $user = $card->student->user;
        $url = StudentCardPage::getUrl(panel: 'app');

        foreach ([new StudentCardIssued($card), new StudentCardRevoked($card)] as $notification) {
            $this->assertStringContainsString($url, json_encode($notification->toDatabase($user), JSON_UNESCAPED_SLASHES));
        }
    }
}
