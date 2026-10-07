<?php

namespace App\Livewire\Forms;

use App\Enums\UserRole;
use App\Models\Country;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Form;

class RegisterForm extends Form
{
    /**
     * Role yang boleh dipilih dari halaman publik. Role admin tidak pernah bisa didaftarkan dari sini.
     */
    public const ROLES = [
        UserRole::Student->value,
        UserRole::Agent->value,
    ];

    public string $role = '';

    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public string $agency_name = '';

    /**
     * Kode negara ISO (master countries), bukan teks bebas.
     */
    public string $country_code = '';

    public bool $consent = false;

    public function isAgent(): bool
    {
        return $this->role === UserRole::Agent->value;
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'role' => ['required', Rule::in(self::ROLES)],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class, 'email')],
            'password' => ['required', 'string', Password::defaults()],
            'password_confirmation' => ['required', function (string $attribute, mixed $value, Closure $fail): void {
                if ($value !== $this->password) {
                    $fail(__('portal.validation.password_mismatch'));
                }
            }],
            'agency_name' => [Rule::requiredIf(fn (): bool => $this->isAgent()), 'nullable', 'string', 'max:255'],
            'country_code' => [
                Rule::requiredIf(fn (): bool => $this->isAgent()),
                'nullable',
                Rule::exists(Country::class, 'code')->where('is_active', true),
            ],
            'consent' => ['accepted'],
        ];
    }

    /**
     * Pesan berbahasa sederhana (R-2.13), mengikuti bahasa aktif.
     *
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'role.required' => __('portal.validation.role'),
            'role.in' => __('portal.validation.role'),
            'name.required' => __('portal.validation.name_required'),
            'email.required' => __('portal.validation.email_required'),
            'email.email' => __('portal.validation.email_email'),
            'email.unique' => __('portal.validation.email_unique'),
            'password.required' => __('portal.validation.password_required'),
            'password.min' => __('portal.validation.password_min'),
            'password_confirmation.required' => __('portal.validation.password_confirmation_required'),
            'agency_name.required' => __('portal.validation.agency_name_required'),
            'country_code.required' => __('portal.validation.country_required'),
            'country_code.exists' => __('portal.validation.country_exists'),
            'consent.accepted' => __('portal.validation.consent'),
            'max' => __('portal.validation.max'),
        ];
    }

    /**
     * Buat akun (dan profil agen bila role agent) dalam satu transaksi.
     */
    public function store(): User
    {
        return DB::transaction(function (): User {
            $user = User::create([
                'name' => $this->name,
                'email' => Str::lower($this->email),
                'password' => $this->password,
                'role' => UserRole::from($this->role),
                'locale' => app()->getLocale(), // bahasa email & notifikasi berikutnya
            ]);

            if ($user->hasRole(UserRole::Agent)) {
                $user->agent()->create([
                    'company_name' => $this->agency_name,
                    'country_code' => $this->country_code,
                    'email' => $user->email,
                ]);
            }

            return $user;
        });
    }
}
