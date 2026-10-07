<?php

namespace App\Models;

use App\Enums\Gender;
use App\Enums\Religion;
use App\Enums\StudentStatus;
use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Student extends Model
{
    use HasUuids, LogsActivity, SoftDeletes;

    protected $attributes = [
        'status' => StudentStatus::Draft->value,
    ];

    protected $fillable = [
        'user_id',
        'agent_id',
        'program_id',
        'academic_period_id',
        'full_name',
        'gender',
        'place_of_birth',
        'date_of_birth',
        'nationality_code',
        'religion',
        'permanent_address',
        'state',
        'post_code',
        'email',
        'phone_number',
        'home_university',
        'home_university_country_code',
        'passport_number',
        'date_of_issued_passport',
        'date_of_passport_expiry',
        'photo',
        'status',
        'revision_note',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'gender' => Gender::class,
            'religion' => Religion::class,
            'date_of_birth' => 'date',
            'date_of_issued_passport' => 'date',
            'date_of_passport_expiry' => 'date',
            'status' => StudentStatus::class,
            'submitted_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }

    /**
     * Pendaftar pemilik data: agen yang mendaftarkan atau mahasiswa mandiri itu sendiri.
     */
    public function isOwnedBy(User $user): bool
    {
        return match ($user->role) {
            UserRole::Agent => $this->agent_id !== null && $this->agent_id === $user->agent?->id,
            UserRole::Student => $this->user_id !== null && $this->user_id === $user->id,
            default => false,
        };
    }

    /**
     * Batasi query sesuai role (dipakai di getEloquentQuery() resource Filament).
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return match ($user->role) {
            UserRole::SuperAdmin => $query,
            UserRole::Admin => $user->faculty_id === null
                ? $query->whereRaw('0 = 1')
                : $query->whereHas('program', fn (Builder $program) => $program->where('faculty_id', $user->faculty_id)),
            UserRole::Agent => $user->agent === null
                ? $query->whereRaw('0 = 1')
                : $query->where('agent_id', $user->agent->id),
            UserRole::Student => $query->where('user_id', $user->id),
        };
    }

    /**
     * Akun yang menerima notifikasi pendaftaran ini: mahasiswa mandiri, atau agen yang mendaftarkannya.
     */
    public function owner(): ?User
    {
        return $this->user ?? $this->agent?->user;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function academicPeriod(): BelongsTo
    {
        return $this->belongsTo(AcademicPeriod::class);
    }

    /**
     * Kewarganegaraan (master negara).
     */
    public function nationality(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'nationality_code', 'code');
    }

    /**
     * Negara tempat universitas asal berada (master negara).
     */
    public function homeUniversityCountry(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'home_university_country_code', 'code');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function loa(): HasOne
    {
        return $this->hasOne(Loa::class);
    }

    public function visaStatus(): HasOne
    {
        return $this->hasOne(VisaStatus::class);
    }
}
