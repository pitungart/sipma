{{--
    Lonceng notifikasi (template): dropdown di bawah lonceng, bukan slide-over bawaan.
    Livewire-nya tetap bawaan Filament (polling, tandai dibaca, hapus); hanya tampilannya diganti.
    Klik item = tandai dibaca lalu buka tautan aksi pertamanya. `teleport` agar panel tidak
    terpotong .fi-topbar (lihat filament.language-switcher).
--}}
@php
    $notifications = $this->getNotifications();
    $unreadNotificationsCount = $this->getUnreadNotificationsCount();
    $tones = ['primary', 'info', 'success', 'warning', 'danger', 'gray'];
@endphp

<div
    @if ($pollingInterval = $this->getPollingInterval())
        wire:poll.{{ $pollingInterval }}
    @endif
    class="flex"
>
    <x-filament::dropdown placement="bottom-end" shift teleport width="sipma-notif" wire:key="sipma-notif">
        <x-slot name="trigger">
            {{ $this->getTrigger()?->with(['unreadNotificationsCount' => $unreadNotificationsCount]) }}
        </x-slot>

        <div class="sipma-notif-head">
            <span class="sipma-notif-title">
                {{ __('filament-notifications::database.modal.heading') }}
                @if ($unreadNotificationsCount)
                    <span class="sipma-pill sipma-tone-danger">{{ __('admin.shell.notifications.unread', ['count' => $unreadNotificationsCount]) }}</span>
                @endif
            </span>
            @if ($unreadNotificationsCount)
                <button type="button" class="sipma-notif-link" wire:click="markAllNotificationsAsRead">
                    {{ __('admin.shell.notifications.mark_all_read') }}
                </button>
            @endif
        </div>

        @if ($notifications->isEmpty())
            <div class="sipma-notif-empty">
                <span class="sipma-tile sipma-tone-gray">
                    <x-filament::icon icon="lucide-bell-off" class="sipma-tile-icon" />
                </span>
                <b>{{ __('filament-notifications::database.modal.empty.heading') }}</b>
                <span>{{ __('admin.shell.notifications.empty') }}</span>
            </div>
        @else
            <ul class="sipma-notif-list">
                @foreach ($notifications as $record)
                    @php
                        $notification = $this->getNotification($record);
                        $tone = in_array($notification->getIconColor(), $tones, true) ? $notification->getIconColor() : 'primary';
                        $url = collect($notification->getActions())->map(fn ($action) => $action->getUrl())->filter()->first();
                        $id = $record->getKey();
                    @endphp
                    <li @class(['sipma-notif-item', 'is-unread' => $record->unread()]) wire:key="sipma-notif-{{ $id }}">
                        <a
                            @if ($url) href="{{ $url }}" @endif
                            class="sipma-notif-main"
                            x-on:click.prevent="await $wire.markNotificationAsRead(@js($id)); @if ($url) window.location.href = @js($url) @endif"
                        >
                            <span class="sipma-tile sipma-tile-sm sipma-tone-{{ $tone }}">
                                <x-filament::icon :icon="$notification->getIcon() ?? 'lucide-bell'" class="sipma-tile-icon" />
                            </span>
                            <span class="sipma-notif-text">
                                <span class="sipma-notif-item-title">{{ str($notification->getTitle())->sanitizeHtml()->toHtmlString() }}</span>
                                @if (filled($notification->getBody()))
                                    <span class="sipma-notif-body">{{ str($notification->getBody())->sanitizeHtml()->toHtmlString() }}</span>
                                @endif
                                <span class="sipma-notif-time">{{ $notification->getDate() }}</span>
                            </span>
                            @if ($record->unread())
                                <span class="sipma-notif-dot" title="{{ __('admin.shell.notifications.unread_one') }}"></span>
                            @endif
                        </a>
                        <button
                            type="button"
                            class="sipma-notif-remove"
                            wire:click="removeNotification(@js($id))"
                            aria-label="{{ __('admin.shell.notifications.remove') }}"
                            title="{{ __('admin.shell.notifications.remove') }}"
                        >
                            <x-filament::icon icon="lucide-x" class="sipma-notif-remove-icon" />
                        </button>
                    </li>
                @endforeach
            </ul>

            <div class="sipma-notif-foot">
                @if ($notifications instanceof \Illuminate\Contracts\Pagination\Paginator && $notifications->hasPages())
                    <x-filament::pagination :paginator="$notifications" />
                @endif
                <button type="button" class="sipma-notif-link is-danger" wire:click="clearNotifications">
                    <x-filament::icon icon="lucide-trash-2" class="sipma-notif-link-icon" />
                    {{ __('admin.shell.notifications.clear') }}
                </button>
            </div>
        @endif
    </x-filament::dropdown>

    @if ($broadcastChannel = $this->getBroadcastChannel())
        <x-filament-notifications::database.echo :channel="$broadcastChannel" />
    @endif
</div>
