<?php

namespace App\Models;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Document extends Model
{
    use HasUuids, LogsActivity;

    /**
     * Batas ukuran file per dokumen dalam kilobyte (sesuai SOP KUI).
     * Daftar 5 dokumen wajib: DocumentType::required().
     */
    public const MAX_FILE_SIZE_KB = 300;

    protected $attributes = [
        'status' => DocumentStatus::Pending->value,
    ];

    protected $fillable = [
        'student_id',
        'type',
        'file_path',
        'original_name',
        'file_size',
        'mime_type',
        'status',
        'revision_note',
        'reviewed_by',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => DocumentType::class,
            'file_size' => 'integer',
            'status' => DocumentStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
