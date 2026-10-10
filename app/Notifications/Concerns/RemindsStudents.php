<?php

namespace App\Notifications\Concerns;

use App\Enums\UserRole;
use App\Filament\Agent\Resources\StudentResource;
use App\Models\Student;
use App\Models\User;
use App\Support\ApplicantUrl;
use Illuminate\Support\Collection;

/**
 * Pengingat untuk satu pendaftar (mahasiswa mandiri) atau ringkasan banyak mahasiswa (agen).
 *
 * @property-read Collection<int, Student> $students
 */
trait RemindsStudents
{
    /**
     * Nama yang disebut di ringkasan agen: maksimal tiga, sisanya "dan n lainnya".
     */
    protected function names(): string
    {
        $names = $this->students->pluck('full_name');

        return $names->count() > 3
            ? $names->take(3)->implode(', ').' '.__('workflow.notifications.and_more', ['count' => $names->count() - 3])
            : $names->implode(', ');
    }

    /**
     * Satu mahasiswa → detailnya; ringkasan agen → tab Mahasiswa saya yang relevan.
     */
    protected function reminderUrl(User $notifiable, string $agentTab): string
    {
        if ($notifiable->hasRole(UserRole::Agent) && $this->students->count() > 1) {
            return StudentResource::getUrl('index', ['activeTab' => $agentTab], panel: 'agent');
        }

        return ApplicantUrl::for($notifiable, $this->students->first());
    }
}
