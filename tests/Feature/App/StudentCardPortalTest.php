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
 * TP-2 F2 & F4: gambar KTM, hak unduh, dan halaman KTM di portal mahasiswa.
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

    public function test_owner_downloads_a_real_jpg_of_card_size(): void
    {
        $card = $this->issued();

        $response = $this->actingAs($card->student->user)->get($this->url($card))->assertOk();

        $this->assertSame('image/jpeg', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('KTM-KTMA-'.now()->year.'-0001.jpg', (string) $response->headers->get('Content-Disposition'));

        $bytes = (string) $response->getContent();
        [$width, $height, $type] = getimagesizefromstring($bytes);

        $this->assertSame(IMAGETYPE_JPEG, $type);
        $this->assertEqualsWithDelta(85.6, $width / 300 * 25.4, 0.1);
        $this->assertEqualsWithDelta(54, $height / 300 * 25.4, 0.1);

        // Header JFIF: satuan inci dan 300 DPI, agar tercetak seukuran kartu
        $this->assertSame([1, 300], array_values(unpack('Cunit/ndensity', substr($bytes, 13, 3))));
    }

    public function test_print_opens_the_image_inline(): void
    {
        $card = $this->issued();

        $response = $this->actingAs($card->student->user)->get($this->url($card, download: false))->assertOk();

        $this->assertStringContainsString('inline', (string) $response->headers->get('Content-Disposition'));
    }

    public function test_extreme_text_stays_on_the_card(): void
    {
        $student = $this->enrolled();
        $student->update(['full_name' => 'Şahin Ñandú Müller-Đặng '.str_repeat('Abdullah Muhammad ', 8)]);
        $student->program->update(['name' => str_repeat('Indonesian Language for Foreign Speakers ', 5)]);
        $student->program->faculty->update(['name' => str_repeat('Fakultas Matematika dan Ilmu Pengetahuan Alam ', 4)]);

        [$width, $height] = getimagesizefromstring(StudentCardDocument::jpeg($this->issued($student->fresh())));

        $this->assertSame([StudentCardDocument::WIDTH, StudentCardDocument::HEIGHT], [$width, $height]);

        $lines = StudentCardDocument::fit(str_repeat('Abdullah Muhammad ', 8), 6.5, 55.5, 2, true);

        $this->assertCount(2, $lines);
        $this->assertStringEndsWith('…', $lines[1]);
        $this->assertSame(['Ana Sato'], StudentCardDocument::fit('Ana   Sato', 9.5, 55.5, 2, true));
        $this->assertStringEndsWith('…', StudentCardDocument::fit(str_repeat('x', 200), 6.2, 55.5, 2)[0]);
    }

    public function test_names_outside_dm_sans_use_the_fallback_font(): void
    {
        foreach (['Ana Sato', 'Şahin Ñandú Müller-Đoàn', 'KTMA-2026-0001'] as $text) {
            $this->assertStringEndsWith('DMSans-Regular.ttf', StudentCardDocument::font($text));
        }

        foreach (['Nguyễn Văn An', 'Đặng', 'Trương', 'Ольга'] as $text) {
            $font = StudentCardDocument::font($text);

            $this->assertStringEndsWith('DejaVuSans.ttf', $font);
            $this->assertFileExists($font);
        }

        $this->assertStringEndsWith('DMSans-Bold.ttf', StudentCardDocument::font('Ana', bold: true));
        $this->assertFileExists(StudentCardDocument::font('Đặng', bold: true));
        $this->assertStringEndsWith('DejaVuSans-Bold.ttf', StudentCardDocument::font('Đặng', bold: true));
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
        $active = StudentCardDocument::jpeg($card);

        $this->assertNull(StudentCardDocument::watermark($card));

        $card->update(['status' => StudentCardStatus::Revoked]);
        $this->assertSame('DICABUT / REVOKED', StudentCardDocument::watermark($card->fresh()));
        $this->assertNotSame($active, StudentCardDocument::jpeg($card->fresh()));

        $card->update(['status' => StudentCardStatus::Expired]);
        $this->assertSame('KEDALUWARSA / EXPIRED', StudentCardDocument::watermark($card->fresh()));
        $this->assertNotSame($active, StudentCardDocument::jpeg($card->fresh()));
    }

    public function test_photo_is_cropped_and_a_missing_or_broken_file_falls_back_to_initials(): void
    {
        $student = $this->enrolled(photo: UploadedFile::fake()->image('wide.png', 1200, 600));

        $photo = StudentCardDocument::photo($student);
        $this->assertSame([248, 331], [imagesx($photo), imagesy($photo)]);

        // Rusak: bukan gambar
        $path = $student->documents()->value('file_path');
        Storage::disk('local')->put($path, 'not an image');
        $this->assertNull(StudentCardDocument::photo($student));

        // Hilang: kartu tetap terbentuk dengan inisial
        Storage::disk('local')->delete($path);
        $this->assertNull(StudentCardDocument::photo($student));
        $this->assertSame('AS', StudentCardDocument::initials('Ana  Sato Putri'));

        $card = $this->issued($student);
        $this->actingAs($student->user)->get($this->url($card))->assertOk();
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
            ->assertSee($this->url($card, download: false).'"', false)
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
            ->assertDontSee('sipma-idcard');
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
