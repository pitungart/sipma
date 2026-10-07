<?php

namespace App\Models;

use App\Enums\PaymentType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Rekening/VA statis tujuan pembayaran. Nominal selalu Rupiah.
 */
class PaymentAccount extends Model
{
    use HasUuids;

    protected $fillable = [
        'bank_name',
        'account_name',
        'va_number',
        'fee_type',
        'program_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'fee_type' => PaymentType::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * Rekening yang berlaku untuk satu jenis biaya pada satu program:
     * rekening khusus program/jenis biaya, atau rekening umum (kolomnya null).
     */
    public function scopeFor(Builder $query, PaymentType $type, ?string $programId = null): Builder
    {
        return $query->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('fee_type')->orWhere('fee_type', $type))
            ->where(fn (Builder $q) => $q->whereNull('program_id')->orWhere('program_id', $programId));
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
