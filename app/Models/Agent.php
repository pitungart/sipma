<?php

namespace App\Models;

use App\Enums\MouStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Agent extends Model
{
    use HasUuids, LogsActivity, SoftDeletes;

    protected $fillable = [
        'user_id',
        'company_name',
        'first_name',
        'last_name',
        'email',
        'phone',
        'address',
        'country',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }

    /**
     * Agen dianggap terverifikasi jika punya minimal satu MOU yang disetujui KUI.
     */
    public function hasApprovedMou(): bool
    {
        return $this->mous()->where('status', MouStatus::Approved)->exists();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Riwayat MOU (upload awal + revisi).
     */
    public function mous(): HasMany
    {
        return $this->hasMany(Mou::class);
    }

    /**
     * Diurutkan created_at lalu id (ordered UUID) sebagai penentu jika waktunya sama.
     */
    public function latestMou(): HasOne
    {
        return $this->hasOne(Mou::class)->ofMany(['created_at' => 'max', 'id' => 'max']);
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }
}
