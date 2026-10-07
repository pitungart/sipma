<?php

namespace App\Notifications;

use App\Filament\Admin\Resources\StudentResource;
use App\Models\Payment;
use App\Models\User;

class PaymentSubmitted extends SipmaNotification
{
    public function __construct(public readonly Payment $payment) {}

    protected function title(): string
    {
        return __('workflow.notifications.payment_submitted.title');
    }

    protected function body(): string
    {
        return __('workflow.notifications.payment_submitted.body', ['name' => $this->payment->student->full_name, 'type' => $this->payment->type->getLabel()]);
    }

    protected function icon(): string
    {
        return 'lucide-wallet';
    }

    protected function tone(): string
    {
        return 'warning';
    }

    /**
     * Tombol "Buka" langsung ke halaman kerja KUI.
     */
    protected function url(User $notifiable): string
    {
        return StudentResource::getUrl('view', ['record' => $this->payment->student], panel: 'admin');
    }
}
