<?php

namespace App\Notifications;

use App\Models\Student;
use App\Models\User;
use App\Support\ApplicantUrl;

class RevisionRequested extends SipmaNotification
{
    public function __construct(public readonly Student $student) {}

    protected function title(): string
    {
        return __('workflow.notifications.revision.title');
    }

    protected function body(): string
    {
        return __('workflow.notifications.revision.body', ['name' => $this->student->full_name, 'note' => $this->student->revision_note ?: __('workflow.notifications.revision.see_documents')]);
    }

    protected function icon(): string
    {
        return 'lucide-triangle-alert';
    }

    protected function tone(): string
    {
        return 'danger';
    }

    /**
     * Tombol "Buka" langsung ke detail pendaftar di panel penerima.
     */
    protected function url(User $notifiable): string
    {
        return ApplicantUrl::for($notifiable, $this->student);
    }
}
