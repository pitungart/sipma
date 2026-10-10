<?php

namespace App\Notifications;

use App\Models\Payment;
use App\Models\User;
use App\Support\ApplicantUrl;

class PaymentRejected extends SipmaNotification
{
    public function __construct(public readonly Payment $payment) {}

    protected function title(): string
    {
        return __('workflow.notifications.payment_rejected.title');
    }

    protected function body(): string
    {
        return __('workflow.notifications.payment_rejected.body', ['name' => $this->payment->student->full_name, 'type' => $this->payment->type->getLabel(), 'note' => $this->payment->rejection_note]);
    }

    protected function icon(): string
    {
        return 'lucide-circle-x';
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
        return ApplicantUrl::for($notifiable, $this->payment->student);
    }
}
