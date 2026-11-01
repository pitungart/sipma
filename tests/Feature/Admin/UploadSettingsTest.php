<?php

namespace Tests\Feature\Admin;

use App\Enums\DocumentType;
use App\Enums\UserRole;
use App\Filament\Admin\Pages\UploadSettings;
use App\Models\Faculty;
use App\Models\Setting;
use App\Models\User;
use App\Support\UploadLimits;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Tests\Feature\Workflow\WorkflowTestCase;

/**
 * Sistem → Unggahan berkas: hanya Super Admin; batas per jenis dokumen (pilihan cepat atau Custom)
 * langsung dipakai validasi unggah.
 */
class UploadSettingsTest extends WorkflowTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_only_super_admin_opens_and_saves_upload_limits(): void
    {
        $faculty = Faculty::create(['name' => 'Fakultas Teknik', 'code' => 'FT']);
        $facultyAdmin = User::factory()->create(['role' => UserRole::Admin, 'faculty_id' => $faculty->id]);
        $this->actingAs($facultyAdmin)->get(UploadSettings::getUrl())->assertForbidden();

        $this->actingAs($this->superAdmin())->get(UploadSettings::getUrl())->assertOk()->assertSee(DocumentType::Transcript->getLabel());

        Livewire::test(UploadSettings::class)
            ->assertFormSet(['photo.preset' => '1024', 'payment_proof.preset' => '2048', 'mou.preset' => '5120'])
            ->fillForm(['photo' => ['preset' => 'custom', 'kb' => 50], 'mou' => ['preset' => 'custom', 'kb' => 99999]])
            ->call('save')
            ->assertHasFormErrors(['photo.kb' => 'min', 'mou.kb' => 'max']);
        $this->assertSame(1024, UploadLimits::document(DocumentType::Photo));

        // Pilihan cepat untuk satu jenis, Custom untuk jenis lain; isian Custom diabaikan bila tidak dipilih
        Livewire::test(UploadSettings::class)
            ->fillForm([
                'photo' => ['preset' => '300', 'kb' => 50],
                'passport' => ['preset' => 'custom', 'kb' => 1536],
                'payment_proof' => ['preset' => '5120'],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(300, UploadLimits::document(DocumentType::Photo));
        $this->assertSame(1536, UploadLimits::document(DocumentType::Passport));
        $this->assertSame(1024, UploadLimits::document(DocumentType::Declaration));
        $this->assertSame(5120, UploadLimits::paymentProof());
        $this->assertStringContainsString('1.5 MB', DocumentType::Passport->hint());

        // Nilai di luar pilihan cepat tampil sebagai Custom saat halaman dibuka lagi
        Livewire::test(UploadSettings::class)
            ->assertFormSet(['photo.preset' => '300', 'passport.preset' => 'custom', 'passport.kb' => 1536]);
    }

    public function test_legacy_single_document_limit_applies_to_unset_document_types(): void
    {
        Setting::create(['key' => 'upload_max_kb.document', 'value' => '500']);
        UploadLimits::save([DocumentType::Photo->value => 300]);

        $this->assertSame(300, UploadLimits::document(DocumentType::Photo));
        $this->assertSame(500, UploadLimits::document(DocumentType::Declaration));
        $this->assertSame(UploadLimits::PAYMENT_PROOF_DEFAULT, UploadLimits::paymentProof());
    }
}
