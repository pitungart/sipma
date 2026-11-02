<?php

namespace App\Models;

use App\Enums\VisaProcessStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class VisaStatus extends Model
{
    use HasUuids, LogsActivity;

    protected $attributes = [
        'status' => VisaProcessStatus::NotStarted->value,
    ];

    protected $fillable = [
        'student_id',
        'status',
        'notes',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => VisaProcessStatus::class,
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['status', 'notes'])->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
