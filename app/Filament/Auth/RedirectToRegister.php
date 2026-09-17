<?php

namespace App\Filament\Auth;

use App\Enums\UserRole;
use Filament\Facades\Filament;
use Filament\Pages\Auth\Register;

/**
 * Agen dan mahasiswa mendaftar di satu halaman portal (/register, App\Livewire\Auth\Register).
 * Halaman register panel hanya meneruskan ke sana, sehingga tautan "Sign up" di login Filament tetap berfungsi.
 */
class RedirectToRegister extends Register
{
    public function mount(): void
    {
        $role = Filament::getCurrentPanel()?->getId() === 'agent'
            ? UserRole::Agent
            : UserRole::Student;

        $this->redirect(route('register', ['role' => $role->value]));
    }
}
