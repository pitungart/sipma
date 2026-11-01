<?php

namespace App\Support;

use App\Enums\UserRole;
use App\Models\User;
use App\Notifications\ApplicationApproved;
use App\Notifications\ApplicationSubmitted;
use App\Notifications\LoaIssued;
use App\Notifications\MouReviewed;
use App\Notifications\MouSubmitted;
use App\Notifications\PaymentRejected;
use App\Notifications\PaymentReminder;
use App\Notifications\PaymentSubmitted;
use App\Notifications\RevisionRequested;
use App\Notifications\StudentEnrolled;
use App\Notifications\SubmissionReminder;

/**
 * Pilihan email per jenis notifikasi (Profil saya → Preferensi). Lonceng panel selalu dikirim;
 * pilihan ini hanya menyaring email, dan baru berlaku bila config('sipma.mail_notifications') aktif.
 * Jenis yang belum pernah diatur dianggap aktif.
 */
final class NotificationPreferences
{
    /**
     * Kunci pilihan per kelas notifikasi.
     */
    public const KEYS = [
        ApplicationSubmitted::class => 'application_submitted',
        PaymentSubmitted::class => 'payment_submitted',
        MouSubmitted::class => 'mou_submitted',
        RevisionRequested::class => 'revision_requested',
        ApplicationApproved::class => 'application_approved',
        PaymentRejected::class => 'payment_rejected',
        LoaIssued::class => 'loa_issued',
        StudentEnrolled::class => 'student_enrolled',
        MouReviewed::class => 'mou_reviewed',
        SubmissionReminder::class => 'reminders',
        PaymentReminder::class => 'reminders',
    ];

    /**
     * Jenis yang memang diterima tiap peran (sesuai penerima di StudentWorkflow & MouWorkflow).
     *
     * @return list<string>
     */
    public static function keysFor(User $user): array
    {
        return match ($user->role) {
            UserRole::SuperAdmin => ['application_submitted', 'payment_submitted', 'mou_submitted'],
            UserRole::Admin => [],
            UserRole::Agent => ['revision_requested', 'application_approved', 'payment_rejected', 'loa_issued', 'student_enrolled', 'reminders', 'mou_reviewed'],
            UserRole::Student => ['revision_requested', 'application_approved', 'payment_rejected', 'loa_issued', 'student_enrolled', 'reminders'],
        };
    }

    /**
     * @return array<string, bool>
     */
    public static function for(User $user): array
    {
        $saved = $user->notification_preferences ?? [];

        return collect(self::keysFor($user))
            ->mapWithKeys(fn (string $key): array => [$key => (bool) ($saved[$key] ?? true)])
            ->all();
    }

    public static function set(User $user, string $key, bool $enabled): void
    {
        abort_unless(in_array($key, self::keysFor($user), true), 422);

        $user->forceFill(['notification_preferences' => [...($user->notification_preferences ?? []), $key => $enabled]])->save();
    }

    public static function wantsMail(User $user, string $notificationClass): bool
    {
        $key = self::KEYS[$notificationClass] ?? null;

        return $key === null || (bool) (($user->notification_preferences ?? [])[$key] ?? true);
    }

    /**
     * Email notifikasi alur aktif di sistem? Bila tidak, pilihan ditampilkan tapi dinonaktifkan.
     */
    public static function mailEnabled(): bool
    {
        return (bool) config('sipma.mail_notifications');
    }
}
