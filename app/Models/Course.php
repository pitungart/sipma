<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Mata kuliah dalam paket suatu program (TP-3). Nilai per mahasiswa tidak disimpan di sini.
 */
class Course extends Model
{
    use HasUuids, SoftDeletes;

    protected $attributes = [
        'is_active' => true,
    ];

    protected $fillable = [
        'program_id',
        'code',
        'name',
        'name_en',
        'credits',
        'semester',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'credits' => 'integer',
            'semester' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Nama sesuai bahasa antarmuka; portal berbahasa Inggris memakai nama resmi bila terjemahannya kosong.
     */
    public function localizedName(): string
    {
        return app()->getLocale() === 'id' ? $this->name : ($this->name_en ?: $this->name);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Urutan paket: semester lalu kode; MK tanpa semester di akhir.
     */
    public function scopePackageOrder(Builder $query): Builder
    {
        return $query->orderByRaw('semester IS NULL')->orderBy('semester')->orderBy('code');
    }

    /**
     * Mahasiswa yang menempuh MK ini.
     */
    public function studentCourses(): HasMany
    {
        return $this->hasMany(StudentCourse::class);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }
}
