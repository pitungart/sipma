<?php

namespace App\Support;

use App\Enums\DocumentStatus;
use App\Enums\MouStatus;
use App\Enums\PaymentStatus;
use App\Enums\StudentCardStatus;
use App\Enums\StudentStatus;
use App\Enums\VisaProcessStatus;
use App\Models\Agent;
use App\Models\Document;
use App\Models\FileTemplate;
use App\Models\Loa;
use App\Models\Mou;
use App\Models\Payment;
use App\Models\Student;
use App\Models\StudentCard;
use App\Models\User;
use App\Models\VisaStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Activity;

/**
 * Mengubah baris activity log menjadi kalimat (R-2.12) — dipakai widget "Aktivitas terbaru"
 * di dashboard, tab "Riwayat" di detail pendaftar, dan tab "Aktivitas" di Profil saya.
 */
final class ActivityDescriber
{
    /**
     * Subjek turunan pendaftar beserta kunci kalimatnya.
     */
    public const CHILD_SUBJECTS = [
        Document::class => 'document',
        Payment::class => 'payment',
        Loa::class => 'loa',
        VisaStatus::class => 'visa',
        StudentCard::class => 'card',
    ];

    /**
     * Riwayat satu pendaftar: perubahan pada dirinya, dokumennya, pembayarannya, dan LOA-nya.
     *
     * @return Collection<int, array{text: string, time: string, at: string, tone: string, icon: string}>
     */
    public static function forStudent(Student $student): Collection
    {
        return Activity::query()
            ->where(fn (Builder $q) => $q
                ->whereMorphedTo('subject', $student)
                ->orWhereHasMorph('subject', array_keys(self::CHILD_SUBJECTS), fn (Builder $child) => $child->where('student_id', $student->getKey())))
            ->with(['causer', 'subject'])
            ->latest()
            ->latest('id')
            ->get()
            ->map(fn (Activity $activity): array => self::describe($activity));
    }

    /**
     * Tindakan yang dilakukan satu pengguna (Profil saya → Aktivitas), dengan kalimat tanpa
     * subjek pelaku ("Menyetujui dokumen …") karena pelakunya pembaca sendiri.
     *
     * @return Collection<int, array{text: string, time: string, at: string, tone: string, icon: string}>
     */
    public static function forCauser(User $user, int $limit = 50): Collection
    {
        return Activity::query()
            ->whereMorphedTo('causer', $user)
            ->with('subject')
            ->latest()
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(fn (Activity $activity): array => self::describe($activity, own: true));
    }

    /**
     * @return array{text: string, time: string, at: string, tone: string, icon: string}
     */
    public static function describe(Activity $activity, bool $own = false): array
    {
        return [
            'text' => $own ? self::ownSentence($activity) : self::sentence($activity),
            'time' => $activity->created_at->diffForHumans(),
            'at' => $activity->created_at->translatedFormat('j M Y, H.i'),
            'tone' => self::tone($activity),
            'icon' => self::icon($activity),
        ];
    }

    public static function sentence(Activity $activity): string
    {
        $subject = $activity->subject;
        $student = $subject instanceof Student ? $subject : $subject?->student;
        $name = $student?->full_name ?? '—';
        $causer = $activity->causer?->name ?? __('admin.dashboard.activity_system');
        $status = $activity->properties['attributes']['status'] ?? null;

        if ($subject instanceof Student && $status !== null && ($state = StudentStatus::tryFrom($status))) {
            return __('admin.dashboard.activity_status', ['name' => $name, 'status' => $state->getLabel()]);
        }

        if ($subject instanceof VisaStatus && $status !== null && ($visa = VisaProcessStatus::tryFrom($status))) {
            return __('admin.dashboard.activity_visa', ['causer' => $causer, 'name' => $name, 'status' => $visa->getLabel()]);
        }

        if ($subject instanceof StudentCard && ($card = self::cardEvent($activity)) !== null) {
            return __("admin.dashboard.activity_card_{$card}", ['causer' => $causer, 'name' => $name]);
        }

        $kind = $subject instanceof Student || $subject === null ? 'student' : self::CHILD_SUBJECTS[$subject::class];
        $event = in_array($activity->event, ['created', 'updated', 'deleted'], true) ? $activity->event : 'updated';

        return __("admin.dashboard.activity_{$event}", [
            'causer' => $causer,
            'subject' => __("admin.dashboard.activity_subject_{$kind}", ['name' => $name]),
        ]);
    }

    /**
     * Kalimat untuk aktivitas milik pembaca sendiri: kata kerja di depan, tanpa nama pelaku.
     * Perubahan status dokumen, pembayaran, dan MOU disebut sebagai keputusannya.
     */
    public static function ownSentence(Activity $activity): string
    {
        $subject = $activity->subject;
        $status = (string) ($activity->properties['attributes']['status'] ?? '');
        $name = self::subjectName($subject);

        $decision = match (true) {
            $activity->event === 'deleted' => null,
            $subject instanceof Student && ($state = StudentStatus::tryFrom($status)) !== null => ['status', ['name' => $name, 'status' => $state->getLabel()]],
            $subject instanceof Document && ($state = DocumentStatus::tryFrom($status)) !== null && $state !== DocumentStatus::Pending => ["document_{$state->value}", ['document' => $subject->type?->getLabel(), 'name' => $name]],
            $subject instanceof Payment && ($state = PaymentStatus::tryFrom($status)) !== null && $state !== PaymentStatus::Pending => ["payment_{$state->value}", ['name' => $name]],
            $subject instanceof Mou && ($state = MouStatus::tryFrom($status)) !== null && $state !== MouStatus::Pending => ["mou_{$state->value}", ['name' => $name]],
            $subject instanceof Loa && $activity->event === 'created' => ['loa_issued', ['name' => $name]],
            $subject instanceof VisaStatus && ($visa = VisaProcessStatus::tryFrom($status)) !== null => ['visa', ['name' => $name, 'status' => $visa->getLabel()]],
            $subject instanceof StudentCard && ($card = self::cardEvent($activity)) !== null => ["card_{$card}", ['name' => $name]],
            default => null,
        };

        if ($decision !== null) {
            return __("profile.activity.{$decision[0]}", $decision[1]);
        }

        $event = in_array($activity->event, ['created', 'updated', 'deleted'], true) ? $activity->event : 'updated';

        return __("profile.activity.{$event}", [
            'subject' => __('profile.activity.subject_'.self::subjectKind($subject), ['name' => $name]),
        ]);
    }

    /**
     * issued | revoked | expired; perubahan KTM lainnya memakai kalimat umum.
     */
    private static function cardEvent(Activity $activity): ?string
    {
        return match (true) {
            $activity->event === 'created' => 'issued',
            ($activity->properties['attributes']['status'] ?? null) === StudentCardStatus::Revoked->value => 'revoked',
            ($activity->properties['attributes']['status'] ?? null) === StudentCardStatus::Expired->value => 'expired',
            default => null,
        };
    }

    private static function subjectKind(mixed $subject): string
    {
        return match (true) {
            $subject instanceof Agent => 'agent',
            $subject instanceof Mou => 'mou',
            $subject instanceof FileTemplate => 'template',
            $subject !== null && isset(self::CHILD_SUBJECTS[$subject::class]) => self::CHILD_SUBJECTS[$subject::class],
            default => 'student',
        };
    }

    private static function subjectName(mixed $subject): string
    {
        return match (true) {
            $subject instanceof Student => $subject->full_name,
            $subject instanceof Agent => $subject->company_name,
            $subject instanceof Mou => $subject->agent?->company_name ?? '—',
            $subject instanceof FileTemplate => $subject->original_name ?? $subject->name,
            $subject !== null && isset(self::CHILD_SUBJECTS[$subject::class]) => $subject->student?->full_name ?? '—',
            default => '—',
        } ?? '—';
    }

    public static function tone(Activity $activity): string
    {
        $status = StudentStatus::tryFrom((string) ($activity->properties['attributes']['status'] ?? ''));

        return match (true) {
            $activity->event === 'deleted' => 'danger',
            $activity->subject instanceof Student && $status !== null => $status->getColor(),
            $activity->subject instanceof Payment => 'warning',
            $activity->subject instanceof Loa => 'violet',
            $activity->subject instanceof VisaStatus => VisaProcessStatus::tryFrom((string) ($activity->properties['attributes']['status'] ?? ''))?->getColor() ?? 'info',
            $activity->subject instanceof StudentCard => match (self::cardEvent($activity)) {
                'revoked' => 'danger',
                'expired' => 'warning',
                default => 'success',
            },
            $activity->subject instanceof Document => 'info',
            $activity->subject instanceof Mou => 'warning',
            default => 'primary',
        };
    }

    public static function icon(Activity $activity): string
    {
        $subject = $activity->subject;
        $status = StudentStatus::tryFrom((string) ($activity->properties['attributes']['status'] ?? ''));

        return match (true) {
            $activity->event === 'deleted' => 'lucide-trash-2',
            $subject instanceof Payment => 'lucide-receipt',
            $subject instanceof Loa => 'lucide-file-check',
            $subject instanceof VisaStatus => 'lucide-plane',
            $subject instanceof StudentCard => 'lucide-id-card',
            $subject instanceof Document => 'lucide-file-text',
            $subject instanceof Mou => 'lucide-handshake',
            $subject instanceof Agent => 'lucide-building-2',
            $subject instanceof FileTemplate => 'lucide-file-down',
            $subject instanceof Student && $status !== null => $status->getIcon(),
            $activity->event === 'created' => 'lucide-user-plus',
            default => 'lucide-user-pen',
        };
    }
}
