<?php

namespace App\Filament\Admin\Widgets;

use App\Models\Student;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Builder;

/**
 * Dasar kartu dashboard: dirender dengan Blade + Tailwind sendiri (template), bukan
 * komponen tabel/statistik Filament. Semua hitungan memakai scope visibleTo yang sama
 * dengan tabelnya (Admin Fakultas: fakultasnya saja).
 */
abstract class DashboardWidget extends Widget
{
    /**
     * Dirender langsung, bukan lazy: kueri agregatnya ringan dan kerangka pemuatan
     * membuat dashboard berkedip sesaat sebelum isinya datang.
     */
    protected static bool $isLazy = false;

    protected function user(): User
    {
        return Filament::auth()->user();
    }

    /**
     * @return Builder<Student>
     */
    protected function students(): Builder
    {
        return Student::query()->visibleTo($this->user());
    }
}
