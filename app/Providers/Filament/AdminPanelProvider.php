<?php

namespace App\Providers\Filament;

use App\Filament\Admin\Pages\Dashboard;
use App\Filament\Auth\RedirectToLogin;
use App\Filament\Support\SipmaTheme;
use App\Http\Middleware\SetLocale;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return SipmaTheme::apply($panel)
            ->default()
            ->id('admin')
            ->path('admin')
            ->login(RedirectToLogin::class) // login terpadu di /login
            ->passwordReset()
            // Grup tampil sebagai label bagian kecil (template); ikon ada di tiap item, jadi
            // grup tidak boleh berikon (Filament menolak ikon di grup dan item sekaligus).
            ->navigationGroups([
                NavigationGroup::make()
                    ->label(fn (): string => __('admin.groups.main'))
                    ->collapsible(false),
                NavigationGroup::make()
                    ->label(fn (): string => __('admin.groups.admissions'))
                    ->collapsible(false),
                NavigationGroup::make()
                    ->label(fn (): string => __('admin.groups.master_data'))
                    ->collapsible(),
            ])
            ->discoverResources(in: app_path('Filament/Admin/Resources'), for: 'App\\Filament\\Admin\\Resources')
            ->discoverPages(in: app_path('Filament/Admin/Pages'), for: 'App\\Filament\\Admin\\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Admin/Widgets'), for: 'App\\Filament\\Admin\\Widgets')
            // Widget dashboard ditemukan lewat discoverWidgets; widget bawaan Filament
            // (kartu akun & info versi) tidak dipakai karena menggantikan isi yang nyata.
            ->widgets([])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                SetLocale::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
