<?php

namespace App\Models;

use App\Enums\UserRole;
use Filament\Facades\Filament;
use Filament\Models\Contracts\FilamentUser;
use Filament\Notifications\Auth\VerifyEmail;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser, MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, HasUuids, Notifiable, SoftDeletes;

    /**
     * Samakan dengan default kolom di database agar user yang baru dibuat
     * (misal langsung login setelah register) punya nilai yang benar.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'role' => UserRole::Student->value,
        'is_active' => true,
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'faculty_id',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * Panel /admin untuk Super Admin + Admin Fakultas, /agent untuk Agen, /app untuk Mahasiswa.
     * Hak akses di dalam panel diatur oleh Policy (app/Policies).
     */
    public function canAccessPanel(Panel $panel): bool
    {
        if (! $this->is_active) {
            return false;
        }

        return $panel->getId() === $this->panelId();
    }

    /**
     * ID panel Filament milik user ini (tujuan redirect setelah daftar/login dan link verifikasi email).
     */
    public function panelId(): string
    {
        return match ($this->role) {
            UserRole::SuperAdmin, UserRole::Admin => 'admin',
            UserRole::Agent => 'agent',
            UserRole::Student => 'app',
        };
    }

    /**
     * Beranda panel milik user (tujuan setelah login/daftar).
     */
    public function panelUrl(): string
    {
        return url(Filament::getPanel($this->panelId())->getPath());
    }

    /**
     * Link verifikasi mengarah ke panel sesuai role. Route bawaan Laravel (verification.verify)
     * tidak dipakai di SIPMA, jadi notifikasi default diganti notifikasi Filament.
     */
    public function sendEmailVerificationNotification(): void
    {
        $panel = Filament::getPanel($this->panelId());

        if (! $panel->hasEmailVerification()) {
            return;
        }

        $notification = app(VerifyEmail::class);
        $notification->url = $panel->getVerifyEmailUrl($this);
        // Email di-queue: kunci bahasa yang aktif saat ini (pilihan pengguna ketika mendaftar).
        $notification->locale(app()->getLocale());

        $this->notify($notification);
    }

    public function hasRole(UserRole ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === UserRole::SuperAdmin;
    }

    public function faculty(): BelongsTo
    {
        return $this->belongsTo(Faculty::class);
    }

    public function agent(): HasOne
    {
        return $this->hasOne(Agent::class);
    }

    public function student(): HasOne
    {
        return $this->hasOne(Student::class);
    }
}
