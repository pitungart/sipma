<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
        'duration_months',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'admission_fee' => 'decimal:2',
            'tuition_fee' => 'decimal:2',
            'duration_months' => 'integer',
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

    /**
     * Program yang bisa dipilih mahasiswa mandiri (UC-04): semua program aktif. Tidak ada periode
     * pendaftaran; program dibuka di bulan yang berbeda-beda (wawancara KUI, 25 Oktober 2026).
     */
    public function scopeOpenForRegistration(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function faculty(): BelongsTo
    {
        return $this->belongsTo(Faculty::class);
    }

    /**
     * Paket mata kuliah program ini (TP-3).
     */
    public function courses(): HasMany
    {
        return $this->hasMany(Course::class);
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    /**
     * Rekening tujuan khusus program ini (rekening umum punya program_id null).
     */
    public function paymentAccounts(): HasMany
    {
        return $this->hasMany(PaymentAccount::class);
    }

    public function mous(): BelongsToMany
    {
        return $this->belongsToMany(Mou::class);
    }
}
