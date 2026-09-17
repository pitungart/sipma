<?php

namespace App\Models;

use App\Enums\MouStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Mou extends Model
{
    use HasUuids, LogsActivity;

    protected $attributes = [
        'status' => MouStatus::Pending->value,
    ];

    protected $fillable = [
        'agent_id',
        'file_path',
        'status',
        'revision_note',
        'verified_by',
        'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => MouStatus::class,
            'verified_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
