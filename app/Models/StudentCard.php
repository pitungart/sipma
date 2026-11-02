<?php

namespace App\Models;

use App\Enums\StudentCardStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * KTM digital. Diterbitkan, diterbitkan ulang, dan dicabut lewat StudentCardWorkflow.
 */
class StudentCard extends Model
{
    use HasUuids, LogsActivity;

    protected $attributes = [
        'status' => StudentCardStatus::Active->value,
    ];

    protected $fillable = [
        'student_id',
        'card_number',
        'issued_at',
        'valid_until',
        'status',
        'issued_by',
        'revoked_at',
        'revoke_reason',
        'replaces_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => StudentCardStatus::class,
            'issued_at' => 'date',
            'valid_until' => 'date',
            'revoked_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['card_number', 'status', 'valid_until', 'revoke_reason'])->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    public function isActive(): bool
    {
        return $this->status === StudentCardStatus::Active;
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    /**
     * KTM lama yang digantikan kartu ini (terbit ulang).
     */
    public function replaces(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replaces_id');
    }
}
