<?php

namespace App\Notifications;

use App\Models\Student;
use App\Models\User;
use App\Support\ApplicantUrl;

class StudentEnrolled extends SipmaNotification
{
    public function __construct(public readonly Student $student) {}

    protected function title(): string
    {
        return __('workflow.notifications.student_enrolled.title');
    }

    protected function body(): string
    {
        return __('workflow.notifications.student_enrolled.body', [
            'name' => $this->student->full_name,
            'program' => $this->student->program?->name,
        ]);
    }

    protected function icon(): string
    {
        return 'lucide-graduation-cap';
    }

    protected function tone(): string
    {
        return 'success';
    }

    protected function url(User $notifiable): string
    {
        return ApplicantUrl::for($notifiable, $this->student);
    }
}
