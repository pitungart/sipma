<?php

namespace Tests\Feature\Admin;

use App\Enums\DocumentType;
use App\Enums\UserRole;
use App\Filament\Admin\Pages\UploadSettings;
use App\Models\Faculty;
use App\Models\FileTemplate;
use App\Models\User;
use App\Support\FileTemplates;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Feature\Workflow\WorkflowTestCase;

/**
 * Template berkas di Sistem → Unggahan berkas: Super Admin mengunggah, mengganti, dan menghapus;
 * semua pengguna yang login hanya mengunduh. MOU tanpa unggahan memakai template bawaan.
 */
class FileTemplatesTest extends WorkflowTestCase
{
    private const WORD = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_super_admin_uploads_replaces_and_deletes_a_template(): void
    {
        $admin = $this->superAdmin();
        $this->actingAs($admin);

        Livewire::test(UploadSettings::class)
            ->assertSee(__('admin.uploads.templates.none'))
            ->callFormComponentAction('declaration.uploadTemplateAction', 'uploadTemplate', data: [
                'file' => UploadedFile::fake()->create('Surat pernyataan.docx', 120, self::WORD),
            ])
            ->assertHasNoFormComponentActionErrors()
            ->assertSee('Surat pernyataan.docx')
            ->assertFormComponentActionVisible('declaration.deleteTemplateAction', 'deleteTemplate');

        $first = FileTemplates::find(DocumentType::Declaration->value);
        $this->assertSame($admin->id, $first->uploaded_by);
        Storage::disk('local')->assertExists($first->file_path);

        // Ganti: berkas lama ikut dihapus
        Livewire::test(UploadSettings::class)
            ->callFormComponentAction('declaration.uploadTemplateAction', 'uploadTemplate', data: [
                'file' => UploadedFile::fake()->create('Surat pernyataan v2.pdf', 80, 'application/pdf'),
            ])
            ->assertHasNoFormComponentActionErrors();

        $second = FileTemplates::find(DocumentType::Declaration->value);
        $this->assertSame('Surat pernyataan v2.pdf', $second->original_name);
        Storage::disk('local')->assertMissing($first->file_path);
        Storage::disk('local')->assertExists($second->file_path);

        Livewire::test(UploadSettings::class)
            ->callFormComponentAction('declaration.deleteTemplateAction', 'deleteTemplate');

        $this->assertFalse(FileTemplates::has(DocumentType::Declaration->value));
        Storage::disk('local')->assertMissing($second->file_path);
    }

    public function test_template_must_be_pdf_or_word_within_the_size_limit(): void
    {
        foreach ([
            UploadedFile::fake()->image('scan.png'),
            UploadedFile::fake()->create('big.pdf', FileTemplates::MAX_KB + 1, 'application/pdf'),
        ] as $file) {
            try {
                FileTemplates::store(DocumentType::Photo->value, $file);
                $this->fail('Template accepted.');
            } catch (ValidationException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame(0, FileTemplate::query()->count());
        FileTemplates::store(DocumentType::Photo->value, UploadedFile::fake()->create('form.doc', 50, 'application/msword'));
        $this->assertTrue(FileTemplates::has(DocumentType::Photo->value));
    }

    public function test_every_signed_in_user_can_download_but_only_super_admin_manages(): void
    {
        FileTemplates::store(DocumentType::MedicalStatement->value, UploadedFile::fake()->create('Surat sehat.pdf', 50, 'application/pdf'));
        $url = FileTemplates::url(DocumentType::MedicalStatement->value);

        $this->get($url)->assertRedirect();

        $faculty = Faculty::create(['name' => 'Fakultas Teknik', 'code' => 'FT']);
        $facultyAdmin = User::factory()->create(['role' => UserRole::Admin, 'faculty_id' => $faculty->id]);

        foreach ([$this->studentUser(), $this->agent()->user, $facultyAdmin] as $user) {
            $this->actingAs($user)->get($url)->assertOk()->assertDownload('surat-sehat.pdf');
        }

        // Admin Fakultas tidak bisa membuka halaman pengaturan, jadi tidak bisa mengunggah atau menghapus
        $this->actingAs($facultyAdmin)->get(UploadSettings::getUrl())->assertForbidden();

        $this->get(FileTemplates::url(DocumentType::Transcript->value))->assertNotFound();
        $this->get(route('files.template', ['name' => 'payment_proof']))->assertNotFound();
    }

    public function test_mou_falls_back_to_the_built_in_template(): void
    {
        $this->actingAs($this->agent()->user);

        $this->assertTrue(FileTemplates::has(FileTemplates::MOU));
        $this->get(FileTemplates::url(FileTemplates::MOU))->assertOk()->assertDownload('MOU-template-KUI-Udayana.pdf');

        FileTemplates::store(FileTemplates::MOU, UploadedFile::fake()->create('MOU KUI 2027.docx', 90, self::WORD));
        $this->get(FileTemplates::url(FileTemplates::MOU))->assertOk()->assertDownload('mou-kui-2027.docx');
    }
}
