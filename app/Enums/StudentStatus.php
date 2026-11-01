<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

/**
 * Warna mengikuti pemetaan status template (sipma-desing-rules.md §1.7); label + ikon wajib.
 *
 * Pendaftaran berakhir di loa_issued; enrolled & completed adalah tahap pasca-LOA (TP-0) yang
 * ditandai admin. Fitur akademik (KTM, rubrik) baru terbuka setelah Aktif (keputusan 1 November 2026).
 */
enum StudentStatus: string implements HasColor, HasIcon, HasLabel
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case InReview = 'in_review';
    case Revision = 'revision';
    case Approved = 'approved';
    case LoaIssued = 'loa_issued';
    case Enrolled = 'enrolled';
    case Completed = 'completed';

    public function getLabel(): string
    {
        return __("enums.student_status.{$this->value}");
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'sand', // abu hangat: belum diajukan, dibedakan dari "Selesai"
            self::Submitted => 'info', // cyan: baru diajukan
            self::InReview => 'primary', // indigo: sedang diverifikasi
            self::Revision => 'danger',
            self::Approved => 'warning', // kuning: menunggu pembayaran VA
            self::LoaIssued => 'violet', // ungu: LOA terbit, menunggu diaktifkan
            self::Enrolled => 'success', // hijau: menjalani program
            self::Completed => 'gray', // abu: program selesai
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Draft => 'lucide-square-pen',
            self::Submitted => 'lucide-send',
            self::InReview => 'lucide-clock',
            self::Revision => 'lucide-triangle-alert',
            self::Approved => 'lucide-circle-check',
            self::LoaIssued => 'lucide-file-check',
            self::Enrolled => 'lucide-graduation-cap',
            self::Completed => 'lucide-badge-check',
        };
    }

    /**
     * Data pendaftaran hanya boleh diubah pendaftar saat masih draft atau diminta revisi.
     */
    public function isEditable(): bool
    {
        return in_array($this, [self::Draft, self::Revision], true);
    }

    /**
     * Sudah diterima: LOA terbit atau sesudahnya.
     */
    public function isAccepted(): bool
    {
        return in_array($this, self::acceptedStatuses(), true);
    }

    /**
     * Gerbang fitur akademik di portal (KTM, rubrik): hanya setelah admin menandai Aktif.
     */
    public function hasAcademicAccess(): bool
    {
        return in_array($this, [self::Enrolled, self::Completed], true);
    }

    /**
     * Status yang tampil di modul Mahasiswa Asing.
     *
     * @return list<self>
     */
    public static function acceptedStatuses(): array
    {
        return [self::LoaIssued, self::Enrolled, self::Completed];
    }
}
