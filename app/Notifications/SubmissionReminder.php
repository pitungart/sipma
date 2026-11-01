<?php

namespace App\Notifications;

use App\Models\Student;
use App\Models\User;
use App\Notifications\Concerns\RemindsStudents;
use Illuminate\Support\Collection;

/**
 * Pengingat terjadwal: pendaftaran belum diajukan (draf / perlu revisi) dan tidak diubah sekian hari.
 */
class SubmissionReminder extends SipmaNotification
{
    use RemindsStudents;

    /**
     * @param  Collection<int, Student>  $students
     */
    public function __construct(
        public readonly Collection $students,
        public readonly int $days,
    ) {}

    protected function title(): string
    {
        return trans_choice('workflow.notifications.submission_reminder.title', $this->students->count());
    }

    protected function body(): string
    {
        $replace = ['days' => $this->days, 'names' => $this->names(), 'count' => $this->students->count()];

        return $this->students->count() > 1
            ? __('workflow.notifications.submission_reminder.body_many', $replace)
            : __('workflow.notifications.submission_reminder.body_one', $replace);
    }

    protected function icon(): string
    {
        return 'lucide-alarm-clock';
    }

    protected function tone(): string
    {
        return 'warning';
    }

    protected function url(User $notifiable): string
    {
        return $this->reminderUrl($notifiable, 'draft');
    }
}
