<?php

namespace App\Models;

use App\Enums\StudentCourseStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Mata kuliah yang ditempuh satu mahasiswa. Diubah lewat AcademicRecordWorkflow.
 */
class StudentCourse extends Model
{
    use HasUuids, LogsActivity;

    protected $attributes = [
        'status' => StudentCourseStatus::Active->value,
    ];

    protected $fillable = [
        'student_id',
        'course_id',
        'status',
        'grade',
        'grade_point',
        'graded_at',
        'graded_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => StudentCourseStatus::class,
            'grade_point' => 'decimal:2',
            'graded_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['course_id', 'status', 'grade', 'notes'])->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    public function isDropped(): bool
    {
        return $this->status === StudentCourseStatus::Dropped;
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * Termasuk MK yang sudah dihapus dari paket, agar riwayat mahasiswa tetap lengkap.
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class)->withTrashed();
    }
}
