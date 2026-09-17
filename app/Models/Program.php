<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Program extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'faculty_id',
        'name',
        'code',
        'slug',
        'admission_fee',
        'tuition_fee',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'admission_fee' => 'decimal:2',
            'tuition_fee' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Admin Fakultas: program fakultasnya. Agen / mahasiswa: program aktif untuk dipilih.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return match ($user->role) {
            UserRole::SuperAdmin => $query,
            UserRole::Admin => $user->faculty_id === null
                ? $query->whereRaw('0 = 1')
                : $query->where('faculty_id', $user->faculty_id),
            UserRole::Agent, UserRole::Student => $query->where('is_active', true),
        };
    }

    public function faculty(): BelongsTo
    {
        return $this->belongsTo(Faculty::class);
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }
}
