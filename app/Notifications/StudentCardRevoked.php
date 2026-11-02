<?php

namespace App\Notifications;

use App\Models\StudentCard;

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
}
