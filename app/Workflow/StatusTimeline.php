<?php

namespace App\Workflow;

use App\Enums\StudentStatus;

/**
 * Langkah pendaftaran untuk garis waktu (UC-01): isi → diajukan → verifikasi → bayar → LOA.
 * Saat diminta revisi, langkah verifikasi berganti menjadi "perlu perbaikan" bernada merah.
 */
final class StatusTimeline
{
    private const STEPS = [
        'draft' => 'lucide-square-pen',
        'submitted' => 'lucide-send',
        'review' => 'lucide-search-check',
        'payment' => 'lucide-wallet',
        'loa' => 'lucide-file-check',
    ];

    /**
     * @return list<array{key: string, number: int, label: string, description: string, icon: string, state: string, tone: string}>
     */
    public static function for(StudentStatus $status): array
    {
        $current = match ($status) {
            StudentStatus::Draft => 'draft',
            StudentStatus::Submitted => 'submitted',
            StudentStatus::InReview, StudentStatus::Revision => 'review',
            StudentStatus::Approved => 'payment',
            StudentStatus::LoaIssued => null, // semua langkah selesai
        };

        $keys = array_keys(self::STEPS);
        $currentIndex = $current === null ? count($keys) : array_search($current, $keys, true);
        $steps = [];

        foreach ($keys as $index => $key) {
            $text = $key === 'review' && $status === StudentStatus::Revision ? 'revision' : $key;

            $steps[] = [
                'key' => $key,
                'number' => $index + 1,
                'label' => __("workflow.timeline.{$text}.label"),
                'description' => __("workflow.timeline.{$text}.description"),
                'icon' => self::STEPS[$key],
                'state' => match (true) {
                    $index < $currentIndex => 'done',
                    $index === $currentIndex => 'current',
                    default => 'upcoming',
                },
                'tone' => $text === 'revision' ? 'danger' : 'primary',
            ];
        }

        return $steps;
    }
}
