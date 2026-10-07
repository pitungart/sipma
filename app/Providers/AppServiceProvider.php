<?php

namespace App\Providers;

use App\Filament\Support\SipmaIcons;
use App\Models\User;
use Filament\Support\Facades\FilamentIcon;
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
