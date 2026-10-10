<?php

namespace App\Workflow;

use App\Enums\MouStatus;
use App\Models\Agent;
use App\Models\Mou;

/**
 * Tahap onboarding agen (BPMN alur 2, A3–A6): isi profil → unggah MOU → persetujuan KUI.
 * MOU ditolak mengembalikan agen ke langkah unggah (loop A5 → A4) dengan nada merah.
 */
final class AgentOnboarding
{
    public const PROFILE = 'profile';

    public const MOU = 'mou';

    public const REJECTED = 'rejected';

    public const PENDING = 'pending';

    public const APPROVED = 'approved';

    private const STEPS = [
        'profile' => 'lucide-building-2',
        'mou' => 'lucide-file-up',
        'approval' => 'lucide-shield-check',
    ];

    public readonly ?Mou $latestMou;

    public function __construct(public readonly ?Agent $agent)
    {
        $this->latestMou = $agent?->latestMou;
    }

    public static function for(?Agent $agent): self
    {
        return new self($agent);
    }

    /**
     * Tahap sekarang: profile | mou | rejected | pending | approved.
     */
    public function stage(): string
    {
        // MOU yang sudah dikirim tetap menunggu KUI walau profil (data lama) belum lengkap;
        // unggah baru — termasuk unggah ulang setelah ditolak — butuh profil lengkap dulu.
        return match (true) {
            $this->agent?->hasApprovedMou() === true => self::APPROVED,
            $this->latestMou?->status === MouStatus::Pending => self::PENDING,
            $this->agent === null || ! $this->agent->isProfileComplete() => self::PROFILE,
            $this->latestMou?->status === MouStatus::Rejected => self::REJECTED,
            default => self::MOU,
        };
    }

    public function canUploadMou(): bool
    {
        return in_array($this->stage(), [self::MOU, self::REJECTED], true);
    }

    /**
     * Agen perlu bertindak (bukan sedang menunggu KUI atau sudah selesai).
     */
    public function needsAction(): bool
    {
        return in_array($this->stage(), [self::PROFILE, self::MOU, self::REJECTED], true);
    }

    /**
     * Langkah untuk x-sipma.status-timeline (bentuk sama dengan StatusTimeline).
     *
     * @return list<array{key: string, number: int, label: string, description: string, icon: string, state: string, tone: string}>
     */
    public function steps(): array
    {
        $stage = $this->stage();
        $currentIndex = match ($stage) {
            self::PROFILE => 0,
            self::MOU, self::REJECTED => 1,
            self::PENDING => 2,
            self::APPROVED => 3, // semua selesai
        };

        $steps = [];

        foreach (array_keys(self::STEPS) as $index => $key) {
            $text = $key === 'mou' && $stage === self::REJECTED ? 'mou_rejected' : $key;

            $steps[] = [
                'key' => $key,
                'number' => $index + 1,
                'label' => __("agent.onboarding.steps.{$text}.label"),
                'description' => __("agent.onboarding.steps.{$text}.description"),
                'icon' => self::STEPS[$key],
                'state' => match (true) {
                    $index < $currentIndex => 'done',
                    $index === $currentIndex => 'current',
                    default => 'upcoming',
                },
                'tone' => $text === 'mou_rejected' ? 'danger' : 'primary',
            ];
        }

        return $steps;
    }
}
