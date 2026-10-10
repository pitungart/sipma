<?php

namespace App\Filament\Support;

use App\Enums\Gender;
use App\Enums\Religion;
use App\Models\Country;
use Closure;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Wizard\Step;
use Filament\Forms\Get;

/**
 * Isian data pendaftar (UC-07) yang dipakai bersama panel admin, agen, dan nanti mahasiswa.
 *
 * Draf cukup berisi nama, email, nomor paspor, dan program (keputusan 8 Oktober 2026, R-3.7);
 * isian lain boleh menyusul dan baru diperiksa SubmissionChecklist saat pendaftaran diajukan.
 */
final class ApplicantForm
{
    /**
     * Empat langkah wizard; langkah program diisi sesuai panel (pilihan program berbeda).
     *
     * @param  list<Component>  $programFields
     * @return list<Step>
     */
    public static function steps(array $programFields, ?string $programHint = null): array
    {
        return [
            Step::make('identity')->label(__('admin.applicant.steps.identity'))->description(__('admin.applicant.steps.identity_hint'))->icon('lucide-user')->columns(2)->schema(self::identityFields()),
            Step::make('contact')->label(__('admin.applicant.steps.contact'))->description(__('admin.applicant.steps.contact_hint'))->icon('lucide-map-pin')->columns(2)->schema(self::contactFields()),
            Step::make('passport')->label(__('admin.applicant.steps.passport'))->description(__('admin.applicant.steps.passport_hint'))->icon('lucide-book-user')->columns(2)->schema(self::passportFields()),
            Step::make('program')->label(__('admin.applicant.steps.program'))->description($programHint ?? __('admin.applicant.steps.program_hint'))->icon('lucide-graduation-cap')->columns(2)->schema($programFields),
        ];
    }

    /**
     * @return list<Component>
     */
    public static function identityFields(): array
    {
        return [
            TextInput::make('full_name')->label(__('workflow.fields.full_name'))->required()->maxLength(255)->columnSpanFull(),
            Select::make('gender')->label(__('workflow.fields.gender'))->options(Gender::class),
            Select::make('nationality_code')->label(__('workflow.fields.nationality_code'))->options(fn (): array => Country::options())->searchable(),
            TextInput::make('place_of_birth')->label(__('workflow.fields.place_of_birth'))->maxLength(255),
            DatePicker::make('date_of_birth')->label(__('workflow.fields.date_of_birth'))->native(false)->maxDate(now()),
            Select::make('religion')->label(__('admin.applicant.fields.religion'))->options(Religion::class)->helperText(__('admin.applicant.religion_hint')),
        ];
    }

    /**
     * @return list<Component>
     */
    public static function contactFields(): array
    {
        return [
            TextInput::make('email')->label(__('workflow.fields.email'))->email()->required()->maxLength(255),
            TextInput::make('phone_number')->label(__('workflow.fields.phone_number'))->tel()->maxLength(30)->helperText(__('admin.applicant.phone_hint')),
            Textarea::make('permanent_address')->label(__('workflow.fields.permanent_address'))->rows(2)->columnSpanFull(),
            TextInput::make('state')->label(__('admin.applicant.fields.state'))->maxLength(100),
            TextInput::make('post_code')->label(__('admin.applicant.fields.post_code'))->maxLength(20),
            TextInput::make('home_university')->label(__('workflow.fields.home_university'))->maxLength(255),
            Select::make('home_university_country_code')->label(__('workflow.fields.home_university_country_code'))->options(fn (): array => Country::options())->searchable(),
        ];
    }

    /**
     * @return list<Component>
     */
    public static function passportFields(): array
    {
        return [
            TextInput::make('passport_number')
                ->label(__('workflow.fields.passport_number'))
                ->required()
                ->maxLength(50)
                ->dehydrateStateUsing(fn (?string $state): ?string => $state === null ? null : mb_strtoupper(trim($state)))
                ->columnSpanFull(),
            DatePicker::make('date_of_issued_passport')->label(__('workflow.fields.date_of_issued_passport'))->native(false)->maxDate(now()),
            DatePicker::make('date_of_passport_expiry')
                ->label(__('workflow.fields.date_of_passport_expiry'))
                ->native(false)
                // Dibandingkan hanya bila tanggal terbit sudah diisi (draf boleh belum lengkap)
                ->after(fn (Get $get): ?string => filled($get('date_of_issued_passport')) ? 'date_of_issued_passport' : null),
        ];
    }

    /**
     * Pilihan program; daftar pilihannya ditentukan panel (semua program aktif / program MOU agen).
     *
     * @param  Closure(): array<string, string>  $options  dievaluasi Filament (boleh meminta $record, $get, …)
     */
    public static function programSelect(Closure $options): Select
    {
        return Select::make('program_id')
            ->label(__('admin.program.label'))
            ->options($options)
            // Program di luar daftar pilihan ditolak walau dikirim paksa lewat Livewire
            ->in(fn (Select $component): array => array_keys($component->getOptions()))
            ->searchable()
            ->required();
    }
}
