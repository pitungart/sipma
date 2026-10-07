<?php

namespace App\Filament\Admin\Widgets;

use App\Enums\PaymentStatus;
use App\Enums\StudentStatus;
use App\Filament\Admin\Resources\StudentResource;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Number;

/**
 * Empat kartu angka kunci: pendaftar, menunggu verifikasi, perlu revisi, LOA terbit.
 * Setiap kartu memuat ikon bertone, angka besar, dan badge + keterangan di bawahnya.
 */
class StatCards extends DashboardWidget
{
    protected static string $view = 'filament.admin.widgets.stat-cards';

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return ['cards' => [
            $this->applicants(),
            $this->waiting(),
            $this->revision(),
            $this->loaIssued(),
        ]];
    }

    /**
     * @return array<string, mixed>
     */
    private function applicants(): array
    {
        $thisMonth = $this->students()->where('created_at', '>=', now()->startOfMonth())->count();
        $lastMonth = $this->students()
            ->whereBetween('created_at', [now()->subMonthNoOverflow()->startOfMonth(), now()->startOfMonth()])
            ->count();

        // Tanpa pembanding bulan lalu persentase tidak bermakna; tampilkan jumlah bulan ini saja.
        $change = $lastMonth === 0 ? null : ($thisMonth - $lastMonth) / $lastMonth * 100;
        $badge = $change === null
            ? '+'.Number::format($thisMonth, locale: app()->getLocale())
            : ($change >= 0 ? '+' : '').Number::percentage($change, precision: 1, locale: app()->getLocale());

        return [
            'label' => __('admin.dashboard.applicants'),
            'url' => StudentResource::getUrl('index', ['activeTab' => 'all']),
            'value' => $this->students()->count(),
            'icon' => 'lucide-users',
            'tone' => 'primary',
            'badge' => $badge,
            'badgeTone' => $thisMonth >= $lastMonth ? 'success' : 'danger',
            'caption' => $lastMonth === 0 ? __('admin.dashboard.this_month') : __('admin.dashboard.vs_last_month'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function waiting(): array
    {
        $query = fn (): Builder => $this->students()->whereIn('status', [StudentStatus::Submitted, StudentStatus::InReview]);

        $avgDays = (float) ($query()->whereNotNull('submitted_at')->get(['submitted_at'])
            ->avg(fn ($student): float => $student->submitted_at->diffInHours(now()) / 24) ?? 0);

        return [
            'label' => __('admin.dashboard.waiting'),
            'url' => StudentResource::getUrl('index', ['activeTab' => 'submitted']),
            'value' => $query()->count(),
            'icon' => 'lucide-clock',
            'tone' => 'warning',
            'badge' => __('admin.dashboard.avg_days', ['days' => Number::format($avgDays, precision: 1, locale: app()->getLocale())]),
            'badgeTone' => 'warning',
            'caption' => __('admin.dashboard.waiting_time'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function revision(): array
    {
        $pendingPayments = Payment::query()
            ->where('status', PaymentStatus::Pending)
            ->whereIn('student_id', $this->students()->select('id'))
            ->count();

        return [
            'label' => __('admin.dashboard.revision'),
            'url' => StudentResource::getUrl('index', ['activeTab' => 'revision']),
            'value' => $this->students()->where('status', StudentStatus::Revision)->count(),
            'icon' => 'lucide-triangle-alert',
            'tone' => 'danger',
            'badge' => __('admin.dashboard.awaiting_payment', ['count' => $pendingPayments]),
            'badgeTone' => 'danger',
            'caption' => __('admin.dashboard.payments'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function loaIssued(): array
    {
        $thisWeek = DB::table('loas')
            ->whereIn('student_id', $this->students()->select('id'))
            ->where('issued_at', '>=', now()->startOfWeek())
            ->count();

        return [
            'label' => __('admin.dashboard.loa_issued'),
            'url' => StudentResource::getUrl('index', ['activeTab' => 'loa_issued']),
            'value' => $this->students()->where('status', StudentStatus::LoaIssued)->count(),
            'icon' => 'lucide-file-check',
            'tone' => 'success',
            'badge' => '+'.$thisWeek,
            'badgeTone' => 'success',
            'caption' => __('admin.dashboard.this_week'),
        ];
    }
}
