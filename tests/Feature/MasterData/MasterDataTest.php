<?php

namespace Tests\Feature\MasterData;

use App\Enums\PaymentType;
use App\Models\AcademicPeriod;
use App\Models\Country;
use App\Models\Faculty;
use App\Models\PaymentAccount;
use App\Models\Program;
use Database\Seeders\CountrySeeder;
use Database\Seeders\FacultySeeder;
use Database\Seeders\ProgramSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MasterDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_country_seeder_fills_the_iso_list_and_can_be_rerun(): void
    {
        $this->seed(CountrySeeder::class);
        $count = Country::count();

        $this->seed(CountrySeeder::class);

        $this->assertSame($count, Country::count());
        $this->assertSame(250, $count); // 249 kode ISO 3166-1 + XK (Kosovo)
        $this->assertSame('Indonesia', Country::find('ID')->name_en);
        $this->assertSame('Belanda', Country::find('NL')->name_id);
    }

    public function test_country_name_and_options_follow_the_active_language(): void
    {
        Country::create(['code' => 'NL', 'name_en' => 'Netherlands', 'name_id' => 'Belanda']);
        Country::create(['code' => 'JP', 'name_en' => 'Japan', 'name_id' => 'Jepang']);

        $this->assertSame('Netherlands', Country::find('NL')->name);
        $this->assertSame(['JP' => 'Japan', 'NL' => 'Netherlands'], Country::options());

        app()->setLocale('id');

        $this->assertSame('Belanda', Country::find('NL')->name);
        $this->assertSame(['NL' => 'Belanda', 'JP' => 'Jepang'], Country::options());
    }

    public function test_inactive_country_is_not_offered(): void
    {
        Country::create(['code' => 'JP', 'name_en' => 'Japan', 'name_id' => 'Jepang', 'is_active' => false]);

        $this->assertSame([], Country::options());
    }

    public function test_faculty_and_program_seeders_are_idempotent(): void
    {
        $this->seed(FacultySeeder::class);
        $this->seed(ProgramSeeder::class);
        $this->seed(FacultySeeder::class);
        $this->seed(ProgramSeeder::class);

        $this->assertSame(13, Faculty::count());
        $this->assertSame(9, Program::count());
        $this->assertNotNull(Program::where('code', 'ND-BIPA')->first()->faculty_id);
    }

    public function test_only_periods_open_today_are_scoped_as_open(): void
    {
        $program = $this->program();

        $open = $this->period($program, 'OPEN', now()->subDay(), now()->addDay());
        $this->period($program, 'CLOSED', now()->subMonth(), now()->subDay());
        $this->period($program, 'SOON', now()->addWeek(), now()->addMonth());
        $this->period($program, 'INACTIVE', now()->subDay(), now()->addDay(), isActive: false);

        $this->assertSame([$open->id], AcademicPeriod::open()->pluck('id')->all());
    }

    public function test_payment_account_scope_matches_specific_and_general_accounts(): void
    {
        $program = $this->program();
        $other = $this->program('ND-OTHER');

        $general = PaymentAccount::create([
            'bank_name' => 'BNI', 'account_name' => 'Universitas Udayana',
            'va_number' => '8001000000001',
        ]);
        $admissionOnly = PaymentAccount::create([
            'bank_name' => 'BNI', 'account_name' => 'Universitas Udayana',
            'va_number' => '8001000000002', 'fee_type' => PaymentType::AdmissionFee,
            'program_id' => $program->id,
        ]);
        PaymentAccount::create([
            'bank_name' => 'BNI', 'account_name' => 'Universitas Udayana',
            'va_number' => '8001000000003', 'program_id' => $other->id,
        ]);
        PaymentAccount::create([
            'bank_name' => 'BNI', 'account_name' => 'Universitas Udayana',
            'va_number' => '8001000000004', 'is_active' => false,
        ]);

        $matched = PaymentAccount::for(PaymentType::AdmissionFee, $program->id)->pluck('id')->all();

        sort($matched);
        $expected = [$general->id, $admissionOnly->id];
        sort($expected);

        $this->assertSame($expected, $matched);
    }

    private function program(string $code = 'ND-BIPA'): Program
    {
        $faculty = Faculty::firstOrCreate(['code' => 'FIB'], ['name' => 'Fakultas Ilmu Budaya']);

        return Program::create([
            'faculty_id' => $faculty->id,
            'name' => 'Program '.$code,
            'code' => $code,
        ]);
    }

    private function period(Program $program, string $code, $opens, $closes, bool $isActive = true): AcademicPeriod
    {
        return AcademicPeriod::create([
            'program_id' => $program->id,
            'name' => $code,
            'code' => $code,
            'registration_opens_at' => $opens,
            'registration_closes_at' => $closes,
            'is_active' => $isActive,
        ]);
    }
}
