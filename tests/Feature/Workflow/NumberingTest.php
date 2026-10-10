<?php

namespace Tests\Feature\Workflow;

use App\Enums\DocumentType;
use App\Enums\NumberSeparator;
use App\Enums\NumberType;
use App\Enums\PaymentType;
use App\Enums\StudentStatus;
use App\Enums\UserRole;
use App\Filament\Admin\Pages\NumberingSettings;
use App\Models\Faculty;
use App\Models\NumberFormat;
use App\Models\User;
use App\Support\Numbering;
use App\Workflow\MouWorkflow;
use App\Workflow\StudentWorkflow;
use Database\Seeders\NumberFormatSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

class NumberingTest extends WorkflowTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->travelTo(now()->setDate(2026, 10, 7));
    }

    public function test_numbers_are_built_from_segments_and_count_per_period(): void
    {
        $this->format(NumberType::Loa, NumberSeparator::Slash, [
            ['type' => 'sequence', 'length' => 3, 'reset' => 'monthly', 'starts_at' => 1],
            ['type' => 'text', 'value' => 'UN14.KUI'],
            ['type' => 'text', 'value' => 'LOA'],
            ['type' => 'month', 'style' => 'roman'],
            ['type' => 'year', 'digits' => 4],
        ]);

        $this->assertSame('001/UN14.KUI/LOA/X/2026', Numbering::next(NumberType::Loa));
        $this->assertSame('002/UN14.KUI/LOA/X/2026', Numbering::next(NumberType::Loa));

        $this->travelTo(now()->setDate(2026, 11, 1));
        $this->assertSame('001/UN14.KUI/LOA/XI/2026', Numbering::next(NumberType::Loa), 'monthly reset');

        // Jenis lain punya penghitung sendiri dan susunan bawaan
        $this->assertSame('SP-26-0001', Numbering::next(NumberType::Application));
    }

    public function test_every_separator_and_segment_type_renders(): void
    {
        $segments = [
            ['type' => 'text', 'value' => 'KW'],
            ['type' => 'year', 'digits' => 2],
            ['type' => 'month', 'style' => 'number'],
            ['type' => 'sequence', 'length' => 2, 'reset' => 'yearly', 'starts_at' => 1],
        ];

        foreach ([
            [NumberSeparator::Slash, 'KW/26/10/01'],
            [NumberSeparator::Dot, 'KW.26.10.01'],
            [NumberSeparator::Dash, 'KW-26-10-01'],
            [NumberSeparator::Underscore, 'KW_26_10_01'],
            [NumberSeparator::Space, 'KW 26 10 01'],
            [NumberSeparator::None, 'KW261001'],
        ] as [$separator, $expected]) {
            $format = new NumberFormat(['type' => NumberType::Receipt, 'separator' => $separator, 'segments' => $segments]);
            $this->assertSame($expected, Numbering::render($format, 1, now()), $separator->value);
        }
    }

    public function test_never_reset_and_start_value_continue_a_paper_register(): void
    {
        $this->format(NumberType::Receipt, NumberSeparator::Dash, [
            ['type' => 'text', 'value' => 'KW'],
            ['type' => 'sequence', 'length' => 4, 'reset' => 'never', 'starts_at' => 250],
        ]);

        $this->assertSame('KW-0250', Numbering::next(NumberType::Receipt));
        $this->travelTo(now()->addYears(2));
        $this->assertSame('KW-0251', Numbering::next(NumberType::Receipt));
    }

    public function test_seeder_provides_the_default_segments_and_separator(): void
    {
        $this->seed(NumberFormatSeeder::class);

        $loa = NumberFormat::for(NumberType::Loa);
        $this->assertTrue($loa->exists);
        $this->assertSame(NumberSeparator::Slash, $loa->separator);
        $this->assertSame(['text', 'text', 'year', 'sequence'], array_column($loa->segments, 'type'));
        $this->assertSame('LOA/SIPMA/2026/0001', Numbering::preview(NumberType::Loa));
        $this->assertSame('SP-26-0001', Numbering::preview(NumberType::Application));
        $this->assertSame('MOU/SIPMA/2026/0001', Numbering::preview(NumberType::Mou));
        $this->assertSame('KW/SIPMA/2026/0001', Numbering::preview(NumberType::Receipt));

        // Menjalankan ulang seeder tidak menimpa susunan yang sudah diubah
        $loa->update(['separator' => NumberSeparator::Dot]);
        $this->seed(NumberFormatSeeder::class);
        $this->assertSame('LOA.SIPMA.2026.0001', Numbering::preview(NumberType::Loa));
    }

    public function test_preview_does_not_consume_a_number_and_existing_numbers_are_skipped(): void
    {
        $this->assertSame('LOA/SIPMA/2026/0001', Numbering::preview(NumberType::Loa));
        $this->assertSame('LOA/SIPMA/2026/0001', Numbering::preview(NumberType::Loa));

        // Nomor yang sudah terbit (mis. data lama) tidak pernah diberikan lagi
        $student = $this->student(status: StudentStatus::Approved);
        $student->loa()->create(['loa_number' => 'LOA/SIPMA/2026/0001', 'file_path' => 'x.pdf']);

        $this->assertSame('LOA/SIPMA/2026/0002', Numbering::preview(NumberType::Loa), 'preview skips it too');
        $this->assertSame('LOA/SIPMA/2026/0002', Numbering::next(NumberType::Loa));
    }

    public function test_each_number_is_issued_at_its_moment(): void
    {
        $workflow = app(StudentWorkflow::class);
        $this->actingAs($admin = $this->superAdmin());

        // Pendaftaran: saat diajukan, dan tetap sama saat diajukan ulang
        $student = $this->student($this->program(admissionFee: 100000));
        foreach (DocumentType::required() as $type) {
            $workflow->uploadDocument($student, $type, $this->file($type));
        }
        $this->assertNull($student->fresh()->registration_number, 'drafts are not numbered');
        $workflow->submit($student);
        $this->assertSame('SP-26-0001', $student->fresh()->registration_number);

        $workflow->startReview($student->fresh());
        $workflow->requestRevision($student->fresh(), 'Check passport');
        $workflow->submit($student->fresh());
        $this->assertSame('SP-26-0001', $student->fresh()->registration_number, 'resubmission keeps its number');

        // Kwitansi: saat pembayaran diverifikasi
        $student->update(['status' => StudentStatus::Approved]);
        $payment = $workflow->submitPayment($student->fresh(), PaymentType::AdmissionFee, $this->pdf());
        $this->assertNull($payment->receipt_number);
        $workflow->verifyPayment($payment);
        $this->assertSame('KW/SIPMA/2026/0001', $payment->fresh()->receipt_number);

        // MOU: saat disetujui, bukan saat ditolak
        $mouFlow = app(MouWorkflow::class);
        $rejected = $mouFlow->reject($mouFlow->submit($this->agent(), $this->pdf()), 'Unsigned');
        $this->assertNull($rejected->mou_number);
        $approved = $mouFlow->approve($mouFlow->submit($this->agent(), $this->pdf()), [$this->program()->id]);
        $this->assertSame('MOU/SIPMA/2026/0001', $approved->mou_number);
    }

    public function test_only_super_admin_can_open_and_save_the_settings(): void
    {
        $faculty = Faculty::create(['name' => 'Fakultas Teknik', 'code' => 'FT']);
        $facultyAdmin = User::factory()->create(['role' => UserRole::Admin, 'faculty_id' => $faculty->id]);

        $this->actingAs($facultyAdmin)->get(NumberingSettings::getUrl())->assertForbidden();
        $this->actingAs($this->superAdmin())->get(NumberingSettings::getUrl(['type' => 'loa']))->assertOk()->assertSee('LOA/SIPMA/2026/0001');

        Livewire::actingAs($this->superAdmin())
            ->test(NumberingSettings::class)
            ->call('selectType', 'loa')
            ->set('formats.loa', ['separator' => 'dot', 'segments' => [
                ['type' => 'text', 'value' => ' UN14 '],
                ['type' => 'text', 'value' => 'LOA'],
                ['type' => 'month', 'style' => 'roman'],
                ['type' => 'sequence', 'length' => '3', 'reset' => 'yearly', 'starts_at' => '1', 'value' => 'leftover'],
            ]])
            ->call('save')
            ->assertHasNoErrors()
            ->assertNotified();

        $loa = NumberFormat::for(NumberType::Loa);
        $this->assertSame(NumberSeparator::Dot, $loa->separator);
        $this->assertSame(['type' => 'sequence', 'length' => 3, 'reset' => 'yearly', 'starts_at' => 1], $loa->segments[3], 'only the fields of its type are kept');
        $this->assertSame('UN14.LOA.X.001', Numbering::preview(NumberType::Loa));
        $this->assertDatabaseCount('number_formats', 1);
    }

    public function test_editing_switches_types_without_losing_changes_and_saves_only_the_open_one(): void
    {
        $page = Livewire::actingAs($this->superAdmin())
            ->test(NumberingSettings::class)
            ->call('selectType', 'loa')
            ->call('setSeparator', 'dash')
            ->call('addSegment', 'month')
            ->call('moveSegment', 4, -1)
            ->call('addSegment', 'sequence'); // ditolak: sudah ada nomor urut

        $this->assertSame(['text', 'text', 'year', 'month', 'sequence'], array_column($page->get('formats.loa.segments'), 'type'));
        $this->assertTrue($page->instance()->isDirty('loa'));
        $this->assertSame('LOA-SIPMA-2026-10-0001', $page->instance()->previewNumber('loa'));

        // Pindah jenis lalu kembali: perubahan LOA tetap ada
        $page->call('selectType', 'mou')->call('removeSegment', 1)->call('selectType', 'loa');
        $this->assertSame('dash', $page->get('formats.loa.separator'));

        $page->call('save')->assertHasNoErrors();
        $this->assertSame('LOA-SIPMA-2026-10-0001', Numbering::preview(NumberType::Loa));
        $this->assertSame('MOU/SIPMA/2026/0001', Numbering::preview(NumberType::Mou), 'MOU was edited but not saved');
        $this->assertTrue($page->instance()->isDirty('mou'));
        $this->assertFalse($page->instance()->isDirty('loa'));

        // Kembalikan ke bawaan
        $page->call('resetToDefault');
        $this->assertSame('LOA/SIPMA/2026/0001', $page->instance()->previewNumber('loa'));
    }

    public function test_saving_asks_for_confirmation_only_when_the_format_is_valid(): void
    {
        $page = Livewire::actingAs($this->superAdmin())
            ->test(NumberingSettings::class)
            ->call('selectType', 'loa');

        // Tidak valid: galat langsung tampil, konfirmasi tidak dibuka
        $page->call('removeSegment', 3)
            ->call('confirmSave')
            ->assertHasErrors(['formats.loa.segments'])
            ->assertActionNotMounted('save');

        // Valid: konfirmasi menampilkan nomor lama → baru; baru tersimpan setelah disetujui
        $page->call('resetToDefault')
            ->call('setSeparator', 'dot')
            ->call('confirmSave')
            ->assertHasNoErrors()
            ->assertActionMounted('save')
            ->assertSee('LOA/SIPMA/2026/0001')
            ->assertSee('LOA.SIPMA.2026.0001');
        $this->assertDatabaseCount('number_formats', 0);

        $page->callMountedAction()->assertNotified();
        $this->assertSame('LOA.SIPMA.2026.0001', Numbering::preview(NumberType::Loa));
    }

    public function test_a_number_needs_exactly_one_sequence_and_filled_text(): void
    {
        $page = Livewire::actingAs($this->superAdmin())->test(NumberingSettings::class)->call('selectType', 'loa');

        $page->set('formats.loa.segments', [['type' => 'text', 'value' => 'LOA'], ['type' => 'year', 'digits' => 4]])
            ->call('save')
            ->assertHasErrors(['formats.loa.segments']);

        $sequence = ['type' => 'sequence', 'length' => 4, 'reset' => 'yearly', 'starts_at' => 1];
        $page->set('formats.loa.segments', [$sequence, ['type' => 'text', 'value' => 'LOA'], $sequence])
            ->call('save')
            ->assertHasErrors(['formats.loa.segments']);

        $page->set('formats.loa.segments', [['type' => 'text', 'value' => ''], $sequence])
            ->call('save')
            ->assertHasErrors(['formats.loa.segments.0.value']);

        $this->assertDatabaseCount('number_formats', 0);
    }

    /**
     * @param  list<array<string, mixed>>  $segments
     */
    private function format(NumberType $type, NumberSeparator $separator, array $segments): NumberFormat
    {
        return NumberFormat::create(['type' => $type, 'separator' => $separator, 'segments' => $segments]);
    }
}
