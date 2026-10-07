<?php

namespace App\Notifications;

use App\Models\Student;

class ApplicationApproved extends SipmaNotification
{
    public function __construct(public readonly Student $student) {}

    protected function title(): string
    {
        return __('workflow.notifications.approved.title');
    }

    protected function body(): string
    {
        return __('workflow.notifications.approved.body', ['name' => $this->student->full_name]);
    }

    protected function icon(): string
    {
        return 'lucide-circle-check';
    }

    protected function tone(): string
    {
        return 'warning';
    }
}
