<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Periode (intake) pendaftaran sebuah program.
 */
class AcademicPeriod extends Model
{
    use HasUuids;

    protected $fillable = [
        'program_id',
        'name',
        'code',
        'registration_opens_at',
        'registration_closes_at',
        'starts_on',
        'ends_on',
        'quota',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'registration_opens_at' => 'date',
            'registration_closes_at' => 'date',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'quota' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Pendaftaran terbuka: periode aktif dan hari ini di dalam rentang pendaftaran.
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->whereDate('registration_opens_at', '<=', now())
            ->whereDate('registration_closes_at', '>=', now());
    }

    /**
     * Admin Fakultas: periode program fakultasnya. Agen / mahasiswa: periode yang sedang dibuka.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return match ($user->role) {
            UserRole::SuperAdmin => $query,
            UserRole::Admin => $user->faculty_id === null
                ? $query->whereRaw('0 = 1')
                : $query->whereHas('program', fn (Builder $program) => $program->where('faculty_id', $user->faculty_id)),
            UserRole::Agent, UserRole::Student => $query->open(),
        };
    }

    /**
     * Kuota kosong berarti tanpa batas.
     */
    /**
     * Periode yang sedang dibuka untuk program ini (yang paling cepat ditutup), untuk pendaftar
     * yang tidak memilih periode sendiri (agen, mahasiswa).
     */
    public static function currentFor(?string $programId): ?self
    {
        return $programId === null ? null : static::query()
            ->open()
            ->where('program_id', $programId)
            ->orderBy('registration_closes_at')
            ->first();
    }

    public function isFull(): bool
    {
        return $this->quota !== null && $this->students()->count() >= $this->quota;
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }
}
