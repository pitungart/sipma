<?php

namespace App\Providers;

use App\Filament\Support\SipmaIcons;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Support\Facades\FilamentIcon;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Seluruh ikon bawaan Filament diarahkan ke Lucide (R-2.17)
        FilamentIcon::register(SipmaIcons::map());

        // Semua select memakai dropdown kustom yang sama dengan Kewarganegaraan, bukan <select>
        // bawaan browser. Kolom cari hanya dipasang per field untuk daftar panjang (> 10 opsi).
        Select::configureUsing(fn (Select $select) => $select->native(false));
        SelectFilter::configureUsing(fn (SelectFilter $filter) => $filter->native(false));

        // Dijalankan sebelum semua Policy: user nonaktif ditolak, Super Admin diizinkan.
        // null = lanjut ke Policy (App\Policies\{Model}Policy, auto-discovered).
        Gate::before(function (User $user, string $ability): ?bool {
            if (! $user->is_active) {
                return false;
            }

            return $user->isSuperAdmin() ? true : null;
        });
    }
}
