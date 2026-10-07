<?php

namespace App\Notifications;

use App\Enums\MouStatus;
use App\Models\Mou;

class MouReviewed extends SipmaNotification
{
    public function __construct(public readonly Mou $mou) {}

    private function approved(): bool
    {
        return $this->mou->status === MouStatus::Approved;
    }

    protected function title(): string
    {
        return __($this->approved() ? 'workflow.notifications.mou_approved.title' : 'workflow.notifications.mou_rejected.title');
    }

    protected function body(): string
    {
        return $this->approved()
            ? __('workflow.notifications.mou_approved.body')
            : __('workflow.notifications.mou_rejected.body', ['note' => $this->mou->revision_note]);
    }

    protected function icon(): string
    {
        return $this->approved() ? 'lucide-circle-check' : 'lucide-circle-x';
    }

    protected function tone(): string
    {
        return $this->approved() ? 'success' : 'danger';
    }
}
