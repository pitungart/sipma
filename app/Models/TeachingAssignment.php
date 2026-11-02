<?php

namespace App\Models;

use App\Enums\TeachingRole;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu baris penugasan dalam SK: dosen × mata kuliah × peran.
 */
class TeachingAssignment extends Model
{
    use HasUuids;

    protected $fillable = [
        'teaching_decree_id',
        'lecturer_id',
        'course_id',
        'role',
    ];

    protected function casts(): array
    {
        return [
            'role' => TeachingRole::class,
        ];
    }

    public function decree(): BelongsTo
    {
        return $this->belongsTo(TeachingDecree::class, 'teaching_decree_id');
    }

    /**
     * Termasuk dosen dan MK yang sudah dihapus, agar SK lama tetap lengkap.
     */
    public function lecturer(): BelongsTo
    {
        return $this->belongsTo(Lecturer::class)->withTrashed();
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class)->withTrashed();
    }
}
