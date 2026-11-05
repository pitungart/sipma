<?php

namespace App\Models;

use App\Enums\Gender;
use App\Enums\Religion;
use App\Enums\StudentCardStatus;
use App\Enums\StudentStatus;
use App\Enums\UserRole;
use App\Support\EnrollmentPeriod;
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
        'registration_number',
        'agent_id',
        'program_id',
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
        'enrolled_at',
        'enrolled_by',
        'completed_at',
        'enrollment_month',
        'enrollment_year',
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
            'enrolled_at' => 'datetime',
            'completed_at' => 'datetime',
            'enrollment_month' => 'integer',
            'enrollment_year' => 'integer',
        ];
    }

    /**
     * Bulan & tahun masuk diisi dari waktu pengajuan saat pendaftaran pertama kali keluar dari draf:
     * biasanya saat diajukan, tetapi juga data yang dibuat langsung berstatus lanjut (seeder / impor).
     * Nilai yang sudah ada (pengajuan ulang, atau diubah admin) tidak ditimpa.
     */
    protected static function booted(): void
    {
        static::saving(function (Student $student): void {
            if ($student->status === StudentStatus::Draft || ! $student->isDirty(['status', 'submitted_at'])) {
                return;
            }

            $at = $student->submitted_at ?? now();
            $student->enrollment_month ??= $at->month;
            $student->enrollment_year ??= $at->year;
        });
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
    public function scopeInEnrollmentPeriod(Builder $query, string $period): Builder
    {
        $parsed = EnrollmentPeriod::parse($period);

        return $parsed === null
            ? $query->whereRaw('0 = 1')
            : $query->where('enrollment_year', $parsed[0])->where('enrollment_month', $parsed[1]);
    }

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

    /**
     * Staf yang menandai mahasiswa Aktif.
     */
    public function enroller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enrolled_by');
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
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

    /**
     * Mata kuliah yang ditempuh, termasuk yang di-drop (TP-3).
     */
    public function studentCourses(): HasMany
    {
        return $this->hasMany(StudentCourse::class);
    }

    /**
     * Seluruh KTM, termasuk yang sudah dicabut atau kedaluwarsa (TP-2).
     */
    public function studentCards(): HasMany
    {
        return $this->hasMany(StudentCard::class);
    }

    /**
     * KTM yang relevan untuk ditampilkan: yang aktif, atau yang terakhir terbit bila tidak ada.
     */
    public function currentCard(): ?StudentCard
    {
        return $this->studentCards()
            ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', [StudentCardStatus::Active->value])
            ->latest()
            ->latest('id')
            ->first();
    }

    public function activeCard(): HasOne
    {
        return $this->hasOne(StudentCard::class)->where('status', StudentCardStatus::Active);
    }
}
