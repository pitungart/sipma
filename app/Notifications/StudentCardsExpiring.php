<?php

namespace App\Notifications;

use App\Enums\StudentCardStatus;
use App\Filament\Admin\Resources\StudentCardResource;
use App\Models\StudentCard;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Pengingat terjadwal ke KUI: ringkasan KTM mahasiswa Aktif yang segera habis, karena mahasiswa
 * tidak bisa memperpanjangnya sendiri.
 */
class StudentCardsExpiring extends SipmaNotification
{
    /**
     * @param  Collection<int, StudentCard>  $cards
     */
    public function __construct(public readonly Collection $cards) {}

    protected function title(): string
    {
        return __('workflow.notifications.cards_expiring.title');
    }

    protected function body(): string
    {
        $names = $this->cards->map(fn (StudentCard $card): ?string => $card->student?->full_name);

        return __('workflow.notifications.cards_expiring.body', [
            'count' => $this->cards->count(),
            'names' => $names->count() > 3
                ? $names->take(3)->implode(', ').' '.__('workflow.notifications.and_more', ['count' => $names->count() - 3])
                : $names->implode(', '),
        ]);
    }

    protected function icon(): string
    {
        return 'lucide-id-card';
    }

    protected function tone(): string
    {
        return 'warning';
    }

    protected function url(User $notifiable): string
    {
        return StudentCardResource::getUrl('index', ['activeTab' => StudentCardStatus::Active->value], panel: 'admin');
    }
}
