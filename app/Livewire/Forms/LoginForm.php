<?php

namespace App\Livewire\Forms;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Form;

class LoginForm extends Form
{
    /**
     * Batas percobaan login per kombinasi email + IP.
     */
    private const MAX_ATTEMPTS = 5;

    public string $email = '';

    public string $password = '';

    public bool $remember = false;

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'email.required' => __('portal.login.validation.email_required'),
            'email.email' => __('portal.login.validation.email_email'),
            'password.required' => __('portal.login.validation.password_required'),
        ];
    }

    /**
     * Masuk dengan email + password. Pesan gagal sengaja sama untuk email maupun password yang salah
     * agar tidak bisa dipakai menebak email terdaftar.
     *
     * @throws ValidationException
     */
    public function authenticate(): User
    {
        $this->ensureIsNotRateLimited();

        $credentials = ['email' => Str::lower($this->email), 'password' => $this->password];

        if (! Auth::attempt($credentials, $this->remember)) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'form.email' => __('portal.login.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());

        /** @var User $user */
        $user = Auth::user();

        // Password sudah terbukti benar, jadi aman memberi tahu bahwa akun dinonaktifkan.
        if (! $user->is_active) {
            Auth::logout();

            throw ValidationException::withMessages([
                'form.email' => __('portal.login.inactive'),
            ]);
        }

        return $user;
    }

    private function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), self::MAX_ATTEMPTS)) {
            return;
        }

        throw ValidationException::withMessages([
            'form.email' => __('portal.login.throttled', ['seconds' => RateLimiter::availableIn($this->throttleKey())]),
        ]);
    }

    private function throttleKey(): string
    {
        return 'login:'.Str::transliterate(Str::lower($this->email)).'|'.request()->ip();
    }
}
