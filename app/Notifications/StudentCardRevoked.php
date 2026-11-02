<?php

namespace App\Notifications;

use App\Filament\App\Pages\StudentCard as StudentCardPage;
use App\Models\StudentCard;
use App\Models\User;

class StudentCardRevoked extends SipmaNotification
{
    public function __construct(public readonly StudentCard $card) {}

    protected function title(): string
    {
        return __('workflow.notifications.card_revoked.title');
    }

    protected function body(): string
    {
        return __('workflow.notifications.card_revoked.body', [
            'number' => $this->card->card_number,
            'reason' => $this->card->revoke_reason,
        ]);
    }

    protected function icon(): string
    {
        return 'lucide-id-card';
    }

    protected function tone(): string
    {
        return 'danger';
    }

    protected function url(User $notifiable): string
    {
        return StudentCardPage::getUrl(panel: 'app');
    }
}
