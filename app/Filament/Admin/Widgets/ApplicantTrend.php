<?php

namespace App\Filament\Admin\Widgets;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Tren pendaftaran per bulan: tahun berjalan (garis indigo + area) dibanding tahun lalu
 * (garis putus-putus). Digambar sebagai SVG sendiri agar sama dengan template dan ikut
 * mode gelap lewat variabel CSS, tanpa Chart.js.
 */
class ApplicantTrend extends DashboardWidget
{
    protected static string $view = 'filament.admin.widgets.applicant-trend';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = ['default' => 'full', 'lg' => 4];

    /** all | agent | self */
    public string $source = 'all';

    // Area plot direntang mengikuti kartu (viewBox 1000×200, tanpa menjaga rasio; tinggi minimal 200px
    // dan mengisi kartu). Teks sumbu, titik, dan tooltip dirender sebagai HTML dengan posisi persen
    // agar ukurannya tidak ikut skala.
    private const WIDTH = 1000;

    private const HEIGHT = 200;

    private const TOP = 8;

    public function setSource(string $source): void
    {
        $this->source = in_array($source, ['all', 'agent', 'self'], true) ? $source : 'all';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $year = CarbonImmutable::now()->year;
        $current = $this->monthlyCounts($year);
        $previous = $this->monthlyCounts($year - 1);

        // Tahun berjalan hanya sampai bulan ini; bulan yang belum terjadi tidak digambar 0.
        $current = array_slice($current, 0, CarbonImmutable::now()->month);

        $max = $this->niceMax(max([...$current, ...$previous, 0]));

        // x dalam persen lebar (pusat tiap kolom bulan); y dalam satuan viewBox (0–200),
        // $yPercent untuk elemen HTML di atas SVG
        $xPercent = fn (int $i): float => round(($i + 0.5) / 12 * 100, 3);
        $y = fn (int $v): float => round(self::HEIGHT - ($v / $max) * (self::HEIGHT - self::TOP), 1);
        $yPercent = fn (int $v): float => round($y($v) / self::HEIGHT * 100, 3);
        $svgX = fn (int $i): float => round($xPercent($i) / 100 * self::WIDTH, 1);

        $points = fn (array $values): string => collect($values)
            ->map(fn (int $v, int $i): string => $svgX($i).','.$y($v))
            ->implode(' ');

        $months = collect(range(1, 12))->map(fn (int $m): array => [
            'x' => $xPercent($m - 1),
            'label' => CarbonImmutable::create($year, $m)->translatedFormat('M'),
            'long' => CarbonImmutable::create($year, $m)->translatedFormat('F'),
            'current' => $current[$m - 1] ?? null,
            'previous' => $previous[$m - 1],
            // Tooltip menempel pada titik tertinggi bulan itu (tahun berjalan bila ada)
            'y' => $yPercent(max($current[$m - 1] ?? 0, $previous[$m - 1])),
        ]);

        $area = $current === []
            ? ''
            : 'M'.$svgX(0).','.self::HEIGHT.' L'.str_replace(' ', ' L', $points($current))
                .' L'.$svgX(count($current) - 1).','.self::HEIGHT.' Z';

        return [
            'year' => $year,
            'sources' => [
                'all' => __('admin.dashboard.source_all'),
                'agent' => __('admin.dashboard.source_agent'),
                'self' => __('admin.dashboard.source_self'),
            ],
            'ticks' => collect(range(0, 4))->map(fn (int $i): array => [
                'y' => $y((int) ($max / 4 * $i)),
                'top' => $yPercent((int) ($max / 4 * $i)),
                'value' => (int) ($max / 4 * $i),
            ]),
            'months' => $months,
            'currentPoints' => $points($current),
            'previousPoints' => $points($previous),
            'area' => $area,
            'dots' => collect($current)->map(fn (int $v, int $i): array => ['x' => $xPercent($i), 'y' => $yPercent($v)]),
            'width' => self::WIDTH,
            'height' => self::HEIGHT,
        ];
    }

    /**
     * Satu query agregat per tahun.
     *
     * @return list<int> 12 angka, Januari–Desember
     */
    private function monthlyCounts(int $year): array
    {
        $month = match (DB::connection()->getDriverName()) {
            'sqlite' => "CAST(strftime('%m', created_at) AS INTEGER)",
            default => 'MONTH(created_at)',
        };

        $counts = $this->students()
            ->when($this->source === 'agent', fn ($q) => $q->whereNotNull('agent_id'))
            ->when($this->source === 'self', fn ($q) => $q->whereNull('agent_id'))
            ->whereBetween('created_at', [
                CarbonImmutable::create($year)->startOfYear(),
                CarbonImmutable::create($year)->endOfYear(),
            ])
            ->selectRaw("{$month} as month, COUNT(*) as total")
            ->groupBy('month')
            ->pluck('total', 'month');

        return collect(range(1, 12))->map(fn (int $m): int => (int) ($counts[$m] ?? 0))->all();
    }

    /**
     * Batas atas sumbu-y yang habis dibagi empat garis bantu.
     */
    private function niceMax(int $value): int
    {
        if ($value <= 4) {
            return 4;
        }

        $magnitude = 10 ** (strlen((string) $value) - 1);
        $nice = (int) (ceil($value / $magnitude) * $magnitude);

        return (int) (ceil($nice / 4) * 4);
    }
}
