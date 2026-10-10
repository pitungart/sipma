<?php

namespace App\Filament\App\Concerns;

use App\Models\Program;
use App\Models\Student;
use Filament\Facades\Filament;

/**
 * Panel /app: satu akun mahasiswa = satu pendaftaran (StudentPolicy::create). Pendaftaran
 * selalu diambil dari akun yang masuk, tidak pernah dari ID yang dikirim browser.
 */
trait InteractsWithApplication
{
    protected static function currentApplication(): ?Student
    {
        return Filament::auth()->user()?->student()->with(['program', 'academicPeriod', 'loa'])->first();
    }

    /**
     * Pilihan program: yang periodenya sedang dibuka (UC-04), ditambah program pendaftaran
     * ini sendiri bila periodenya sudah ditutup. Biaya tidak ditampilkan (permintaan staf KUI).
     *
     * @return array<string, string>
     */
    protected static function programOptions(?Student $application = null): array
    {
        $programs = Program::query()->openForRegistration()->orderBy('name')->pluck('name', 'id')->all();

        if ($application?->program && ! isset($programs[$application->program_id])) {
            $programs[$application->program_id] = $application->program->name;
        }

        return $programs;
    }
}
