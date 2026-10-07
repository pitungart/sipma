<?php

namespace App\Support;

use App\Enums\StudentStatus;
use App\Models\Document;
use App\Models\Loa;
use App\Models\Payment;
use App\Models\Student;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Activity;

/**
 * Mengubah baris activity log menjadi kalimat (R-2.12) — dipakai widget "Aktivitas terbaru"
 * di dashboard dan tab "Riwayat" di detail pendaftar.
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
    ];

    /**
     * Riwayat satu pendaftar: perubahan pada dirinya, dokumennya, pembayarannya, dan LOA-nya.
     *
     * @return Collection<int, array{text: string, time: string, at: string, tone: string}>
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
     * @return array{text: string, time: string, at: string, tone: string}
     */
    public static function describe(Activity $activity): array
    {
        return [
            'text' => self::sentence($activity),
            'time' => $activity->created_at->diffForHumans(),
            'at' => $activity->created_at->translatedFormat('j M Y, H.i'),
            'tone' => self::tone($activity),
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

        $kind = $subject instanceof Student || $subject === null ? 'student' : self::CHILD_SUBJECTS[$subject::class];
        $event = in_array($activity->event, ['created', 'updated', 'deleted'], true) ? $activity->event : 'updated';

        return __("admin.dashboard.activity_{$event}", [
            'causer' => $causer,
            'subject' => __("admin.dashboard.activity_subject_{$kind}", ['name' => $name]),
        ]);
    }

    public static function tone(Activity $activity): string
    {
        return match (true) {
            $activity->event === 'deleted' => 'danger',
            $activity->subject instanceof Payment => 'warning',
            $activity->subject instanceof Loa => 'success',
            $activity->subject instanceof Document => 'info',
            default => 'primary',
        };
    }
}
