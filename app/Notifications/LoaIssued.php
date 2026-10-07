<?php

namespace App\Notifications;

use App\Models\Student;

class LoaIssued extends SipmaNotification
{
    public function __construct(public readonly Student $student) {}

    protected function title(): string
    {
        return __('workflow.notifications.loa_issued.title');
    }

    protected function body(): string
    {
        return __('workflow.notifications.loa_issued.body', ['name' => $this->student->full_name]);
    }

    protected function icon(): string
    {
        return 'lucide-file-check';
    }

    protected function tone(): string
    {
        return 'success';
    }
}
