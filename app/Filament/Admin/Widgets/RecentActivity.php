<?php

namespace App\Filament\Admin\Widgets;

use App\Models\Student;
use App\Support\ActivityDescriber;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Activity;

/**
 * Aktivitas terbaru dari activity log (pendaftar, dokumen, pembayaran, LOA), dibatasi pada
 * pendaftar yang boleh dilihat pengguna. Ditulis sebagai kalimat, bukan catatan basis data.
 */
class RecentActivity extends DashboardWidget
{
    protected static string $view = 'filament.admin.widgets.recent-activity';

    protected static ?int $sort = 6;

    protected int|string|array $columnSpan = ['default' => 'full', 'lg' => 3];

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return ['items' => $this->items()];
    }

    /**
     * @return Collection<int, array<string, string>>
     */
    private function items(): Collection
    {
        $visible = fn (Builder $student): Builder => $student->visibleTo($this->user());

        return Activity::query()
            ->where(fn (Builder $q) => $q
                ->whereHasMorph('subject', [Student::class], $visible)
                ->orWhereHasMorph('subject', array_keys(ActivityDescriber::CHILD_SUBJECTS), fn (Builder $child) => $child->whereHas('student', $visible)))
            ->with(['causer', 'subject'])
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (Activity $activity): array => ActivityDescriber::describe($activity));
    }
}
