<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Penyimpanan berkas pribadi (dokumen pendaftar, bukti bayar, MOU, LOA) di disk privat
 * `storage/app/private` (R-4.5). Berkas tidak pernah punya URL publik; aksesnya lewat
 * PrivateFileController yang memeriksa Policy (R-4.10).
 */
final class PrivateFiles
{
    public const DISK = 'local';

    /**
     * Simpan unggahan dengan nama acak (nama asli disimpan terpisah, tidak dipakai di path).
     *
     * @return array{file_path: string, original_name: string, file_size: int, mime_type: ?string}
     */
    public static function store(UploadedFile $file, string $directory): array
    {
        return [
            'file_path' => $file->store($directory, self::DISK),
            'original_name' => $file->getClientOriginalName(),
            'file_size' => (int) $file->getSize(),
            'mime_type' => $file->getMimeType(),
        ];
    }

    public static function delete(?string $path): void
    {
        if (filled($path)) {
            Storage::disk(self::DISK)->delete($path);
        }
    }

    public static function exists(?string $path): bool
    {
        return filled($path) && Storage::disk(self::DISK)->exists($path);
    }

    /**
     * Folder per pemilik agar berkas satu pendaftar mudah ditelusuri dan dihapus bersama.
     */
    public static function studentDirectory(string $studentId, string $kind): string
    {
        return "students/{$studentId}/{$kind}";
    }

    public static function agentDirectory(string $agentId, string $kind): string
    {
        return "agents/{$agentId}/{$kind}";
    }
}
