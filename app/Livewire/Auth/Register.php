<?php

namespace App\Livewire\Auth;

use App\Livewire\Forms\RegisterForm;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Halaman daftar tunggal untuk mahasiswa mandiri dan agen mitra (UC-05).
 * Pola formulir mengikuti .agents/sipma-desing-rules.md §3.
 */
#[Layout('components.layouts.portal')]
class Register extends Component
{
    /**
     * Batas percobaan daftar per IP per menit.
     */
    private const MAX_ATTEMPTS = 5;

    public RegisterForm $form;

    /**
     * R-3.3: ringkasan error hanya muncul setelah submit gagal, bukan saat validasi blur.
     */
    public bool $showErrorSummary = false;

    public function mount(): void
    {
        if (Auth::check()) {
            $this->redirectToPanel(Auth::user());

            return;
        }

        // ?role=agent|student dari tautan "Sign up" di halaman login panel
        $role = request()->query('role');

        if (in_array($role, RegisterForm::ROLES, true)) {
            $this->form->role = $role;
        }
    }

    /**
     * R-3.6: validasi per field saat blur (wire:model.blur).
     */
    public function updated(string $property): void
    {
        if ($property === 'form.role') {
            $this->resetValidation(['form.agency_name', 'form.country']);
        }

        if (str_starts_with($property, 'form.')) {
            $this->validateOnly($property);
        }
    }

    public function register(): void
    {
        try {
            $this->ensureIsNotRateLimited();
            $this->form->validate();
        } catch (ValidationException $exception) {
            $this->showErrorSummary = true;
            $this->dispatch('form-invalid');

            throw $exception;
        }

        $user = $this->form->store();

        // Listener bawaan Laravel memanggil User::sendEmailVerificationNotification().
        event(new Registered($user));

        Auth::login($user);
        session()->regenerate();

        $this->redirectToPanel($user);
    }

    public function render(): View
    {
        return view('livewire.auth.register')->title(__('portal.meta.title'));
    }

    private function ensureIsNotRateLimited(): void
    {
        $key = 'register:'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            throw ValidationException::withMessages([
                'form.email' => __('portal.register.throttled', ['seconds' => RateLimiter::availableIn($key)]),
            ]);
        }

        RateLimiter::hit($key);
    }

    private function redirectToPanel(User $user): void
    {
        $this->redirect($user->panelUrl());
    }
}
