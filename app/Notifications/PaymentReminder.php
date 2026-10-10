<?php

namespace App\Notifications;

use App\Enums\PaymentType;
use App\Models\Student;
use App\Models\User;
use App\Notifications\Concerns\RemindsStudents;
use Illuminate\Support\Collection;

/**
 * Pengingat terjadwal: pendaftaran disetujui tetapi masih ada biaya tanpa bukti / bukti ditolak.
 * VA Unud berlaku 24 jam, jadi pengingat berjeda harian (SIPMA_PAYMENT_REMINDER_EVERY).
 */
class PaymentReminder extends SipmaNotification
{
    use RemindsStudents;

    /**
     * @param  Collection<int, Student>  $students
     * @param  list<string>  $feeTypes  nilai PaymentType yang belum dibayar (pengingat satu mahasiswa);
     *                                  labelnya dibentuk saat dikirim agar ikut bahasa penerima
     */
    public function __construct(
        public readonly Collection $students,
        public readonly array $feeTypes = [],
    ) {}

    protected function title(): string
    {
        return __('workflow.notifications.payment_reminder.title');
    }

    protected function body(): string
    {
        return $this->students->count() > 1
            ? __('workflow.notifications.payment_reminder.body_many', ['count' => $this->students->count(), 'names' => $this->names()])
            : __('workflow.notifications.payment_reminder.body_one', [
                'name' => $this->students->first()->full_name,
                'fees' => collect($this->feeTypes)->map(fn (string $type): string => PaymentType::from($type)->getLabel())->implode(', '),
            ]);
    }

    protected function icon(): string
    {
        return 'lucide-wallet';
    }

    protected function tone(): string
    {
        return 'warning';
    }

    protected function url(User $notifiable): string
    {
        return $this->reminderUrl($notifiable, 'approved');
    }
}
