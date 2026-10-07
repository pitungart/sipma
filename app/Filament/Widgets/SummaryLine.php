<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

/**
 * Satu baris ringkasan di atas kartu tabel: teks biasa dengan angka ditebalkan,
 * bukan kartu KPI berwarna (R-1.10 — layar tidak perlu jadi riuh).
 */
abstract class SummaryLine extends Widget
{
    protected static string $view = 'filament.widgets.summary-line';

    protected int|string|array $columnSpan = 'full';

    /**
     * Dirender langsung, bukan lazy: satu baris teks tidak sepadan dengan kerangka pemuatan
     * yang justru tampil seperti kartu kosong sebelum isinya datang.
     */
    protected static bool $isLazy = false;

    /**
     * @return list<array{value: int|string, label: string}>
     */
    abstract public function summaryItems(): array;

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return ['items' => $this->summaryItems()];
    }
}
