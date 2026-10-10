<?php

namespace App\Notifications;

use App\Models\User;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification as PanelNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notifikasi SIPMA: lonceng panel (database, format Filament) + email.
 *
 * Laravel mengirim dalam bahasa penerima (User::preferredLocale), jadi semua teks dibentuk
 * dengan __() di dalam method — bukan di constructor — agar ikut bahasa tersebut.
 *
 * Lonceng selalu langsung (koneksi sync); email langsung juga, kecuali SIPMA_QUEUE_MAIL=true
 * yang mengirimnya lewat antrean agar SMTP yang gagal tidak menggagalkan aksi di panel.
 */
abstract class SipmaNotification extends Notification implements ShouldQueue
{
    use Queueable;

    abstract protected function title(): string;

    abstract protected function body(): string;

    abstract protected function icon(): string;

    /**
     * success | warning | danger | info | primary
     */
    abstract protected function tone(): string;

    /**
     * Lonceng panel selalu; email hanya bila config('sipma.mail_notifications') aktif
     * (bawaan mati saat APP_DEBUG=true). Alur tetap berjalan tanpa email.
     *
     * @return list<string>
     */
    public function via(User $notifiable): array
    {
        return config('sipma.mail_notifications') ? ['database', 'mail'] : ['database'];
    }

    /**
     * @return array<string, string>
     */
    public function viaConnections(): array
    {
        return [
            'database' => 'sync',
            'mail' => config('sipma.queue_mail') ? (string) config('queue.default') : 'sync',
        ];
    }

    /**
     * Tujuan tombol "Buka": panel milik penerima. Layar detailnya menyusul di fase berikutnya.
     */
    protected function url(User $notifiable): string
    {
        return $notifiable->panelUrl();
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(User $notifiable): array
    {
        return PanelNotification::make()
            ->title($this->title())
            ->body($this->body())
            ->icon($this->icon())
            ->iconColor($this->tone())
            ->actions([
                Action::make('open')
                    ->label(__('workflow.notifications.open'))
                    ->url($this->url($notifiable))
                    ->markAsRead(),
            ])
            ->getDatabaseMessage();
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title().' · SIPMA')
            ->greeting(__('workflow.notifications.greeting', ['name' => $notifiable->name]))
            ->line($this->body())
            ->action(__('workflow.notifications.open'), $this->url($notifiable))
            ->salutation(__('workflow.notifications.salutation'));
    }
}
