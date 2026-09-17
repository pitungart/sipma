<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Faculty extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'code',
    ];

    public function programs(): HasMany
    {
        return $this->hasMany(Program::class);
    }

    /**
     * Admin Fakultas yang ditugaskan ke fakultas ini.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
