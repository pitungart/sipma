<?php

namespace App\Filament\Auth;

use Filament\Pages\Auth\Login;

/**
 * Semua role masuk lewat satu halaman portal (/login, App\Livewire\Auth\Login).
 * Halaman login tiap panel hanya meneruskan ke sana — termasuk saat tamu membuka panel atau setelah logout.
 */
class RedirectToLogin extends Login
{
    public function mount(): void
    {
        $this->redirect(route('login'));
    }
}
