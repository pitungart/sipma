<?php

namespace App\Notifications;

use App\Models\Student;
use App\Models\User;
use App\Support\StudentAccounts;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Undangan membuat password portal untuk mahasiswa yang didaftarkan agen. Selalu lewat email
 * (seperti reset password), tidak mengikuti saklar notifikasi email.
 */
class StudentAccountInvitation extends SipmaNotification
{
    public function __construct(public readonly Student $student) {}

    public function via(User $notifiable): array
    {
        return ['mail'];
    }

    protected function title(): string
    {
        return __('workflow.notifications.student_invitation.title');
    }

    protected function body(): string
    {
        return __('workflow.notifications.student_invitation.body', [
            'name' => $this->student->full_name,
            'program' => $this->student->program?->name,
        ]);
    }

    protected function icon(): string
    {
        return 'lucide-key-round';
    }

    protected function tone(): string
    {
        return 'success';
    }

    protected function url(User $notifiable): string
    {
        return StudentAccounts::invitationUrl($notifiable);
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title().' · SIPMA')
            ->greeting(__('workflow.notifications.greeting', ['name' => $notifiable->name]))
            ->line($this->body())
            ->action(__('workflow.notifications.student_invitation.action'), $this->url($notifiable))
            ->line(__('workflow.notifications.student_invitation.expiry', [
                'days' => config('sipma.invitation_days'),
                'email' => $notifiable->email,
            ]))
            ->salutation(__('workflow.notifications.salutation'));
    }
}
