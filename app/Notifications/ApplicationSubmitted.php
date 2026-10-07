<?php

namespace App\Notifications;

use App\Filament\Admin\Resources\StudentResource;
use App\Models\Student;
use App\Models\User;

class ApplicationSubmitted extends SipmaNotification
{
    public function __construct(public readonly Student $student, public readonly bool $resubmitted = false) {}

    protected function title(): string
    {
        return __('workflow.notifications.submitted.title');
    }

    protected function body(): string
    {
        return __('workflow.notifications.submitted.body', ['name' => $this->student->full_name, 'program' => $this->student->program?->name, 'again' => $this->resubmitted ? __('workflow.notifications.submitted.again') : '']);
    }

    protected function icon(): string
    {
        return 'lucide-inbox';
    }

    protected function tone(): string
    {
        return 'info';
    }

    /**
     * Tombol "Buka" langsung ke halaman kerja KUI.
     */
    protected function url(User $notifiable): string
    {
        return StudentResource::getUrl('view', ['record' => $this->student], panel: 'admin');
    }
}
