<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Master negara ISO 3166-1 alpha-2. Primary key-nya kode ISO, bukan UUID.
 *
 * @property string $code
 */
class Country extends Model
{
    protected $primaryKey = 'code';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'code',
        'name_en',
        'name_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Nama sesuai bahasa aktif, dengan nama Inggris sebagai cadangan.
     */
    protected function name(): Attribute
    {
        return Attribute::get(fn (): string => app()->getLocale() === 'id'
            ? ($this->name_id ?: $this->name_en)
            : $this->name_en);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Urut sesuai bahasa aktif agar daftar pilihan mudah dibaca.
     */
    public function scopeOrderByName(Builder $query): Builder
    {
        return $query->orderBy(app()->getLocale() === 'id' ? 'name_id' : 'name_en');
    }

    /**
     * Daftar pilihan untuk select: [kode => nama].
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return static::query()->active()->orderByName()->get()->pluck('name', 'code')->all();
    }

    /**
     * Mahasiswa dengan kewarganegaraan negara ini.
     */
    public function students(): HasMany
    {
        return $this->hasMany(Student::class, 'nationality_code', 'code');
    }

    /**
     * Mahasiswa yang universitas asalnya berada di negara ini.
     */
    public function homeUniversityStudents(): HasMany
    {
        return $this->hasMany(Student::class, 'home_university_country_code', 'code');
    }

    public function agents(): HasMany
    {
        return $this->hasMany(Agent::class, 'country_code', 'code');
    }
}
