<?php

namespace App\Models;

use App\Enums\MouStatus;
use Illuminate\Database\Eloquent\Builder;
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

    /**
     * Isian minimal sebelum agen boleh mengunggah MOU (BPMN S1 "Validasi profil agen").
     * Nama belakang & email kontak opsional; email kontak bawaannya email login.
     */
    public const REQUIRED_PROFILE_FIELDS = ['company_name', 'first_name', 'phone', 'address', 'country_code'];

    /**
     * Dikunci setelah MOU disetujui — MOU dibuat atas nama entitas ini (keputusan 7 Oktober 2026).
     */
    public const IDENTITY_FIELDS = ['company_name', 'country_code'];

    protected $fillable = [
        'user_id',
        'company_name',
        'first_name',
        'last_name',
        'email',
        'phone',
        'address',
        'country_code',
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

    /**
     * Gerbang menu pendaftar agen (UC-14): terbuka setelah MOU disetujui KUI (BPMN S3).
     */
    public function canRegisterStudents(): bool
    {
        return $this->hasApprovedMou();
    }

    /**
     * Program yang boleh dipilih agen untuk mahasiswanya: program aktif yang dicakup MOU-nya
     * yang sudah disetujui (catatan UC-04: agen tidak memilih program di luar MOU).
     *
     * @return Builder<Program>
     */
    public function allowedPrograms(): Builder
    {
        return Program::query()
            ->where('is_active', true)
            ->whereHas('mous', fn (Builder $mou) => $mou
                ->where('agent_id', $this->getKey())
                ->where('status', MouStatus::Approved))
            ->orderBy('name');
    }

    public function isIdentityLocked(): bool
    {
        return $this->hasApprovedMou();
    }

    /**
     * @return list<string>
     */
    public function missingProfileFields(): array
    {
        return array_values(array_filter(
            self::REQUIRED_PROFILE_FIELDS,
            fn (string $field): bool => blank($this->getAttribute($field)),
        ));
    }

    public function isProfileComplete(): bool
    {
        return $this->missingProfileFields() === [];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Negara kedudukan agen (master negara).
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'country_code', 'code');
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
