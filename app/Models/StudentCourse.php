<?php

namespace App\Models;

use App\Enums\Grade;
use App\Enums\StudentCourseStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;
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
        'graded_credits',
        'graded_at',
        'graded_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => StudentCourseStatus::class,
            'grade' => Grade::class,
            'graded_credits' => 'integer',
            'grade_point' => 'decimal:2',
            'graded_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['course_id', 'status', 'grade', 'notes'])->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    /**
     * SKS yang dipakai: salinan saat dinilai, atau SKS paket selama belum ada nilai.
     */
    public function effectiveCredits(): ?int
    {
        return $this->grade !== null ? $this->graded_credits : $this->course?->credits;
    }

    /**
     * Urutan tampil: yang di-drop di akhir, lalu semester dan kode seperti paketnya.
     *
     * @param  Collection<int, self>  $rows
     * @return Collection<int, self>
     */
    public static function inPackageOrder(Collection $rows): Collection
    {
        return $rows->sortBy(fn (self $row): array => [
            $row->isDropped() ? 1 : 0,
            $row->course?->semester === null ? 1 : 0,
            $row->course?->semester,
            $row->course?->code,
        ])->values();
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
