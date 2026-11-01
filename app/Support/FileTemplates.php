<?php

namespace App\Support;

use App\Enums\DocumentType;
use App\Models\FileTemplate;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Template berkas (Sistem → Unggahan berkas, keputusan 26 Oktober 2026): Super Admin mengunggah,
 * mengganti, dan menghapus template per jenis dokumen pendaftar dan untuk MOU agen; peran lain
 * hanya mengunduh. Berkas di disk privat, diunduh lewat route files.template (wajib login).
 *
 * MOU selalu punya template: selama belum diunggah, berkas bawaan di resources/ yang dipakai.
 */
final class FileTemplates
{
    public const MOU = 'mou';

    public const EXTENSIONS = ['pdf', 'doc', 'docx'];

    public const MIME_TYPES = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];

    public const MAX_KB = 5120;

    /** Template MOU sementara (dummy) — dipakai sampai Super Admin mengunggah template resmi KUI */
    public const MOU_FALLBACK = 'templates/mou-template.pdf';

    private const DIRECTORY = 'templates';

    /**
     * Nama yang boleh punya template: semua jenis dokumen, ditambah MOU.
     *
     * @return list<string>
     */
    public static function names(): array
    {
        return [...array_map(fn (DocumentType $type): string => $type->value, DocumentType::cases()), self::MOU];
    }

    public static function isValidName(string $name): bool
    {
        return in_array($name, self::names(), true);
    }

    /**
     * Template yang diunggah, per nama.
     *
     * @return array<string, FileTemplate>
     */
    public static function all(): array
    {
        return FileTemplate::query()->get()->keyBy('name')->all();
    }

    /**
     * Nama yang punya template unggahan (tanpa template bawaan MOU), untuk menampilkan tombol unduh.
     *
     * @return list<string>
     */
    public static function uploaded(): array
    {
        return FileTemplate::query()->pluck('name')->all();
    }

    public static function find(string $name): ?FileTemplate
    {
        return FileTemplate::query()->find($name);
    }

    /**
     * Ada template yang bisa diunduh (MOU selalu ada lewat berkas bawaan).
     */
    public static function has(string $name): bool
    {
        return $name === self::MOU || FileTemplate::query()->whereKey($name)->exists();
    }

    public static function store(string $name, UploadedFile $file, ?User $by = null): FileTemplate
    {
        abort_unless(self::isValidName($name), 404);

        Validator::validate(['file' => $file], ['file' => self::rules()]);

        $stored = PrivateFiles::store($file, self::DIRECTORY);
        $old = self::find($name)?->file_path;

        $template = DB::transaction(fn (): FileTemplate => FileTemplate::query()->updateOrCreate(
            ['name' => $name],
            [...$stored, 'uploaded_by' => $by?->getKey()],
        ));

        // Berkas lama dihapus setelah data baru tersimpan
        if ($old !== null && $old !== $stored['file_path']) {
            PrivateFiles::delete($old);
        }

        return $template;
    }

    public static function delete(string $name): void
    {
        $template = self::find($name);

        if ($template !== null) {
            $template->delete();
            PrivateFiles::delete($template->file_path);
        }
    }

    /**
     * @return list<string>
     */
    public static function rules(): array
    {
        return ['file', 'mimes:'.implode(',', self::EXTENSIONS), 'extensions:'.implode(',', self::EXTENSIONS), 'max:'.self::MAX_KB];
    }

    public static function label(string $name): string
    {
        return DocumentType::tryFrom($name)?->getLabel() ?? __('admin.uploads.fields.mou');
    }

    public static function url(string $name): string
    {
        return route('files.template', ['name' => $name]);
    }
}
