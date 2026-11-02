<?php

namespace App\Models;

use App\Enums\DecreePeriod;
use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * SK Mengajar (TP-4). Catatan administrasi; tidak menentukan siapa yang boleh mengisi nilai (K3).
 */
class TeachingDecree extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'faculty_id',
        'decree_number',
        'decree_date',
        'document',
        'period_start',
        'period_end',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'decree_date' => 'date',
            'period_start' => 'date',
            'period_end' => 'date',
        ];
    }

    /**
     * Super Admin: semua. Admin Fakultas: SK fakultasnya. Role lain tidak melihat SK.
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

    public function scopeInPeriod(Builder $query, DecreePeriod $period): Builder
    {
        return match ($period) {
            DecreePeriod::Upcoming => $query->whereDate('period_start', '>', today()),
            DecreePeriod::Ended => $query->whereDate('period_end', '<', today()),
            DecreePeriod::Current => $query->whereDate('period_start', '<=', today())->whereDate('period_end', '>=', today()),
        };
    }

    public function period(): DecreePeriod
    {
        return match (true) {
            $this->period_start->isAfter(today()) => DecreePeriod::Upcoming,
            $this->period_end->isBefore(today()) => DecreePeriod::Ended,
            default => DecreePeriod::Current,
        };
    }

    /**
     * Satu baris per simpan di Profil saya → Aktivitas. Nomor ikut disimpan karena subjek yang
     * terhapus tidak lagi bisa dimuat.
     */
    public function logActivity(string $event): void
    {
        activity()
            ->performedOn($this)
            ->event($event)
            ->withProperties(['decree_number' => $this->decree_number])
            ->log($event);
    }

    public function faculty(): BelongsTo
    {
        return $this->belongsTo(Faculty::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(TeachingAssignment::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
