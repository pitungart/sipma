<?php

namespace App\Notifications;

use App\Models\StudentCard;

/**
 * KTM terbit. Bila menggantikan KTM lama, alasannya ikut disebut.
 */
class StudentCardIssued extends SipmaNotification
{
    public function __construct(public readonly StudentCard $card, public readonly ?string $reason = null) {}

    protected function title(): string
    {
        return __('workflow.notifications.card_'.($this->reason === null ? 'issued' : 'reissued').'.title');
    }

    protected function body(): string
    {
        return __('workflow.notifications.card_'.($this->reason === null ? 'issued' : 'reissued').'.body', [
            'number' => $this->card->card_number,
            'date' => $this->card->valid_until->translatedFormat('j F Y'),
            'reason' => $this->reason,
        ]);
    }

    protected function icon(): string
    {
        return 'lucide-id-card';
    }

    protected function tone(): string
    {
        return 'success';
    }
}
