<?php

namespace App\Livewire\Auth;

use App\Livewire\Forms\LoginForm;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Satu halaman login untuk semua role (UC-06). Setelah masuk, user diarahkan ke panel sesuai role:
 * Super Admin & Admin Fakultas → /admin, Agen → /agent, Mahasiswa → /app.
 */
#[Layout('components.layouts.portal')]
class Login extends Component
{
    public LoginForm $form;

    /**
     * R-3.3: ringkasan error hanya muncul setelah submit gagal.
     */
    public bool $showErrorSummary = false;

    public function mount(): void
    {
        if (Auth::check()) {
            $this->redirect(Auth::user()->panelUrl());
        }
    }

    public function login(): void
    {
        try {
            $this->form->validate();
            $user = $this->form->authenticate();
        } catch (ValidationException $exception) {
            $this->showErrorSummary = true;
            $this->dispatch('form-invalid');

            throw $exception;
        }

        session()->regenerate();

        $this->redirect($this->destinationFor($user));
    }

    public function render(): View
    {
        return view('livewire.auth.login')->title(__('portal.login.meta_title'));
    }

    /**
     * Kembali ke halaman yang tadinya dituju hanya jika halaman itu berada di panel milik user
     * (dan di host yang sama); selain itu ke beranda panelnya.
     */
    private function destinationFor(User $user): string
    {
        $intended = session()->pull('url.intended');

        if (! is_string($intended) || parse_url($intended, PHP_URL_HOST) !== request()->getHost()) {
            return $user->panelUrl();
        }

        $panelPath = '/'.trim(Filament::getPanel($user->panelId())->getPath(), '/');
        $intendedPath = parse_url($intended, PHP_URL_PATH) ?? '';

        return $intendedPath === $panelPath || str_starts_with($intendedPath, $panelPath.'/')
            ? $intended
            : $user->panelUrl();
    }
}
