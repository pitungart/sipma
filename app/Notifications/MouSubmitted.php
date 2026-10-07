<?php

namespace App\Notifications;

use App\Filament\Admin\Resources\AgentResource;
use App\Models\Mou;
use App\Models\User;

class MouSubmitted extends SipmaNotification
{
    public function __construct(public readonly Mou $mou) {}

    protected function title(): string
    {
        return __('workflow.notifications.mou_submitted.title');
    }

    protected function body(): string
    {
        return __('workflow.notifications.mou_submitted.body', ['agency' => $this->mou->agent->company_name]);
    }

    protected function icon(): string
    {
        return 'lucide-file-text';
    }

    protected function tone(): string
    {
        return 'info';
    }

    /**
     * Tombol "Buka" langsung ke halaman kerja KUI.
     */
    protected function url(User $notifiable): string
    {
        return AgentResource::getUrl('index', ['activeTab' => 'pending'], panel: 'admin');
    }
}
