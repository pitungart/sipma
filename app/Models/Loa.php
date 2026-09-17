<?php

namespace App\Models;

use App\Enums\LoaStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Loa extends Model
{
    use HasUuids, LogsActivity;

    protected $attributes = [
        'status' => LoaStatus::Pending->value,
    ];

    protected $fillable = [
        'student_id',
        'loa_number',
        'file_path',
        'status',
        'issued_by',
        'issued_at',
        'downloaded_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => LoaStatus::class,
            'issued_at' => 'datetime',
            'downloaded_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }
}
