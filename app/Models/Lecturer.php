<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Dosen pengajar (TP-4). Hanya data untuk SK Mengajar; dosen tidak punya akun SIPMA (K3).
 */
class Lecturer extends Model
{
    use HasUuids, SoftDeletes;

    protected $attributes = [
        'is_active' => true,
    ];

    protected $fillable = [
        'faculty_id',
        'name',
        'nip',
        'email',
        'phone',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Super Admin: semua. Admin Fakultas: dosen fakultasnya. Role lain tidak melihat data dosen.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return match ($user->role) {
            UserRole::SuperAdmin => $query,
            UserRole::Admin => $user->faculty_id === null
                ? $query->whereRaw('0 = 1')
                : $query->where('faculty_id', $user->faculty_id),
            default => $query->whereRaw('0 = 1'),
        };
    }

    public function faculty(): BelongsTo
    {
        return $this->belongsTo(Faculty::class);
    }
}
