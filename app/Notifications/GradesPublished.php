<?php

namespace App\Notifications;

use App\Filament\App\Pages\AcademicRubric;
use App\Models\User;

/**
 * Satu notifikasi per simpan, bukan per mata kuliah, agar mahasiswa tidak kebanjiran.
 */
class GradesPublished extends SipmaNotification
{
    public function __construct(public readonly int $count) {}

    protected function title(): string
    {
        return __('workflow.notifications.grades_published.title');
    }

    protected function body(): string
    {
        return trans_choice('workflow.notifications.grades_published.body', $this->count, ['count' => $this->count]);
    }

    protected function icon(): string
    {
        return 'lucide-book-open';
    }

    protected function tone(): string
    {
        return 'success';
    }

    protected function url(User $notifiable): string
    {
        return AcademicRubric::getUrl(panel: 'app');
    }
}
