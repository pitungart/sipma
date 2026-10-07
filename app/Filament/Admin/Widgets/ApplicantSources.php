<?php

namespace App\Filament\Admin\Widgets;

use Illuminate\Support\Number;

/**
 * Sumber pendaftar: lewat agen mitra vs mandiri, sebagai donut + legenda.
 */
class ApplicantSources extends DashboardWidget
{
    protected static string $view = 'filament.admin.widgets.applicant-sources';

    protected static ?int $sort = 7;

    protected int|string|array $columnSpan = ['default' => 'full', 'lg' => 3];

    private const RADIUS = 52;

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $total = $this->students()->count();
        $agent = $this->students()->whereNotNull('agent_id')->count();
        $self = $total - $agent;
        $agencies = $this->students()->whereNotNull('agent_id')->distinct()->count('agent_id');

        $circumference = 2 * M_PI * self::RADIUS;
        $agentLength = $total === 0 ? 0 : $agent / $total * $circumference;
        $selfLength = $total === 0 ? 0 : $self / $total * $circumference;

        $percent = fn (int $part): string => $total === 0
            ? '0%'
            : Number::percentage($part / $total * 100, locale: app()->getLocale());

        return [
            'total' => $total,
            'radius' => self::RADIUS,
            'agentDash' => round($agentLength, 2).' '.round($circumference, 2),
            'selfDash' => round($selfLength, 2).' '.round($circumference, 2),
            'selfOffset' => -round($agentLength, 2),
            'rows' => [
                ['label' => __('admin.dashboard.source_agent_long'), 'value' => $agent, 'percent' => $percent($agent), 'tone' => 'primary'],
                ['label' => __('admin.dashboard.source_self_long'), 'value' => $self, 'percent' => $percent($self), 'tone' => 'warning'],
            ],
            'agencies' => trans_choice('admin.dashboard.agencies', $agencies, ['count' => $agencies]),
        ];
    }
}
