<?php

namespace App\Support;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Models\Student;
use App\Models\StudentCard;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * PDF KTM digital (ukuran ID-1) dan gambar yang ditanam di kartunya. Foto dan logo berupa data URI,
 * sehingga pratinjau layar dan PDF memakai komponen yang sama tanpa URL publik.
 */
final class StudentCardDocument
{
    /**
     * ID-1: 85,6 × 54 mm dalam point.
     */
    private const PAPER = [0, 0, 242.65, 153.07];

    private const PHOTO_WIDTH = 300;

    private const PHOTO_HEIGHT = 400;

    /**
     * Foto lebih besar dari ini tidak dibuka, agar satu unggahan tidak menghabiskan memori.
     */
    private const MAX_PIXELS = 40_000_000;

    public static function pdf(StudentCard $card): DomPdf
    {
        // Metrik font hasil olahan dompdf disimpan di folder cache yang tidak ikut git
        File::ensureDirectoryExists($fonts = storage_path('framework/cache/dompdf-fonts'));

        return Pdf::loadView('pdf.student-card', ['card' => $card])
            ->setOption(['font_dir' => $fonts, 'font_cache' => $fonts, 'isFontSubsettingEnabled' => true, 'isRemoteEnabled' => false, 'isPhpEnabled' => false])
            ->setPaper(self::PAPER);
    }

    public static function filename(StudentCard $card): string
    {
        return 'KTM-'.trim((string) preg_replace('/[^A-Za-z0-9]+/', '-', $card->card_number), '-').'.pdf';
    }

    public static function logo(): string
    {
        return 'data:image/png;base64,'.base64_encode(File::get(public_path('images/logo-unud-card.png')));
    }

    /**
     * Pas Foto yang sudah disetujui, dipotong ke 3×4 dan diperkecil. Kosong bila berkasnya hilang
     * atau rusak; kartu lalu menampilkan inisial.
     */
    public static function photo(Student $student): ?string
    {
        $path = $student->documents()
            ->where('type', DocumentType::Photo)
            ->where('status', DocumentStatus::Approved)
            ->latest()
            ->value('file_path');

        if (! PrivateFiles::exists($path)) {
            return null;
        }

        $bytes = Storage::disk(PrivateFiles::DISK)->get($path);
        $size = @getimagesizefromstring($bytes);

        if ($size === false || $size[0] * $size[1] > self::MAX_PIXELS) {
            return null;
        }

        $source = @imagecreatefromstring($bytes);

        if ($source === false) {
            return null;
        }

        return self::jpegDataUri(self::cropToCard($source, $size[0], $size[1]));
    }

    public static function initials(string $name): string
    {
        return Str::of($name)
            ->explode(' ')
            ->filter()
            ->take(2)
            ->map(fn (string $word): string => mb_strtoupper(mb_substr($word, 0, 1)))
            ->implode('');
    }

    /**
     * Potongan tengah 3×4 di atas latar putih (PNG transparan tidak menjadi hitam).
     */
    private static function cropToCard(\GdImage $source, int $width, int $height): \GdImage
    {
        $cropWidth = (int) min($width, round($height * self::PHOTO_WIDTH / self::PHOTO_HEIGHT));
        $cropHeight = (int) min($height, round($width * self::PHOTO_HEIGHT / self::PHOTO_WIDTH));

        $target = imagecreatetruecolor(self::PHOTO_WIDTH, self::PHOTO_HEIGHT);
        imagefill($target, 0, 0, (int) imagecolorallocate($target, 255, 255, 255));
        imagecopyresampled(
            $target, $source, 0, 0,
            (int) (($width - $cropWidth) / 2), (int) (($height - $cropHeight) / 2),
            self::PHOTO_WIDTH, self::PHOTO_HEIGHT, $cropWidth, $cropHeight,
        );

        return $target;
    }

    private static function jpegDataUri(\GdImage $image): string
    {
        ob_start();
        imagejpeg($image, null, 85);

        return 'data:image/jpeg;base64,'.base64_encode((string) ob_get_clean());
    }
}
