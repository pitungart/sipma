<?php

namespace App\Support;

use App\Enums\UserRole;
use App\Filament\Admin\Resources\StudentResource as AdminStudentResource;
use App\Filament\Agent\Resources\StudentResource as AgentStudentResource;
use App\Filament\App\Pages\MyApplication;
use App\Models\Student;
use App\Models\User;

/**
 * Halaman detail pendaftar di panel milik penerima (tombol "Buka" notifikasi).
 */
final class ApplicantUrl
{
    public static function for(User $user, Student $student): string
    {
        return match ($user->role) {
            UserRole::SuperAdmin, UserRole::Admin => AdminStudentResource::getUrl('view', ['record' => $student], panel: 'admin'),
            UserRole::Agent => AgentStudentResource::getUrl('view', ['record' => $student], panel: 'agent'),
            UserRole::Student => MyApplication::getUrl(panel: 'app'),
        };
    }
}
