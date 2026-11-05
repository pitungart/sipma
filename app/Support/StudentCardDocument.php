<?php

namespace App\Support;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\StudentCardStatus;
use App\Models\Student;
use App\Models\StudentCard;
use GdImage;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * KTM digital sebagai JPG seukuran kartu ID-1 pada 300 DPI, digambar dengan GD. Inilah satu-satunya
 * bentuk kartu: pratinjau portal, unduhan, dan ZIP memakai gambar yang sama. Teksnya dua bahasa dan
 * tetap, tidak mengikuti bahasa antarmuka.
 */
final class StudentCardDocument
{
    /**
     * ID-1: 85,6 × 54 mm.
     */
    public const WIDTH = 1011;

    public const HEIGHT = 638;

    private const DPI = 300;

    private const WIDTH_MM = 85.6;

    private const PT_TO_MM = 25.4 / 72;

    private const BODY_LEFT = 27.0;

    private const BODY_WIDTH = 55.5;

    private const PHOTO_WIDTH = 248;

    private const PHOTO_HEIGHT = 331;

    /**
     * Foto lebih besar dari ini tidak dibuka, agar satu unggahan tidak menghabiskan memori.
     */
    private const MAX_PIXELS = 40_000_000;

    private const INDIGO = [75, 77, 214];

    private const TINT = [238, 238, 253];

    private const INK = [32, 41, 58];

    private const MUTED = [109, 114, 127];

    private const WHITE = [255, 255, 255];

    private const RED = [192, 47, 47];

    /**
     * Huruf yang dimiliki DM Sans (hasil pemeriksaan fontnya). Teks dengan huruf lain, misalnya nama
     * Vietnam, memakai DejaVu Sans agar tidak tampil sebagai kotak.
     */
    private const DM_SANS_CHARACTERS = '/^[\x{0020}-\x{007E}\x{00A0}-\x{00AC}\x{00AE}-\x{0107}\x{010A}-\x{011B}\x{011E}-\x{0123}\x{0126}-\x{0133}\x{0136}\x{0137}\x{0139}-\x{0148}\x{014A}-\x{015B}\x{015E}-\x{0165}\x{0168}-\x{017E}\x{2013}\x{2014}\x{2018}-\x{201A}\x{201C}-\x{201E}\x{2026}]*$/u';

    public static function jpeg(StudentCard $card): string
    {
        $card->loadMissing(['student.program.faculty', 'student.nationality']);

        $image = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
        imageresolution($image, self::DPI);
        imagefill($image, 0, 0, self::color($image, self::WHITE));

        self::drawHeader($image);
        self::drawPhoto($image, $card->student);
        self::drawDetails($image, $card);
        self::rectangle($image, 0, 52.5, self::WIDTH_MM, 54, self::INDIGO);
        self::drawWatermark($image, $card);

        ob_start();
        imagejpeg($image, null, 90);

        return (string) ob_get_clean();
    }

    public static function filename(StudentCard $card): string
    {
        return 'KTM-'.self::safeNumber($card).'.jpg';
    }

    /**
     * Nama berkas di dalam ZIP: nomor lebih dulu agar urut, nama agar mudah dikenali.
     */
    public static function archiveEntry(StudentCard $card): string
    {
        $name = Str::limit(Str::slug((string) $card->student?->full_name), 60, '');

        return self::safeNumber($card).($name !== '' ? '_'.$name : '').'.jpg';
    }

    public static function watermark(StudentCard $card): ?string
    {
        return match ($card->status) {
            StudentCardStatus::Revoked => 'DICABUT / REVOKED',
            StudentCardStatus::Expired => 'KEDALUWARSA / EXPIRED',
            default => null,
        };
    }

    /**
     * Pas Foto yang sudah disetujui, dipotong ke 3×4 dan diperkecil. Kosong bila berkasnya hilang
     * atau rusak; kartu lalu menampilkan inisial.
     */
    public static function photo(Student $student): ?GdImage
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

        return $source === false ? null : self::cropToCard($source, $size[0], $size[1]);
    }

    /**
     * Pas Foto 3×4 sebagai JPG, untuk ringkasan detail mahasiswa.
     */
    public static function photoJpeg(Student $student): ?string
    {
        $photo = self::photo($student);

        if ($photo === null) {
            return null;
        }

        ob_start();
        imagejpeg($photo, null, 90);

        return (string) ob_get_clean();
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
     * Pecah teks agar muat di lebar kartu. Kartu tidak bisa memanjang, jadi baris terakhir dipotong
     * dengan elipsis bila teksnya masih tersisa.
     *
     * @return list<string>
     */
    public static function fit(string $text, float $points, float $widthMm, int $maxLines, bool $bold = false): array
    {
        $words = preg_split('/\s+/u', trim($text), flags: PREG_SPLIT_NO_EMPTY) ?: [];
        $lines = [];
        $line = '';

        foreach ($words as $word) {
            $candidate = $line === '' ? $word : $line.' '.$word;

            if (self::widthMm($candidate, $points, $bold) <= $widthMm) {
                $line = $candidate;

                continue;
            }

            if ($line !== '') {
                $lines[] = $line;
            }

            $line = $word;
        }

        if ($line !== '') {
            $lines[] = $line;
        }

        $kept = array_slice($lines, 0, $maxLines);

        if ($kept === []) {
            return [];
        }

        $last = array_key_last($kept);

        if (count($lines) > $maxLines || self::widthMm($kept[$last], $points, $bold) > $widthMm) {
            $kept[$last] = self::ellipsis($kept[$last], $points, $widthMm, $bold);
        }

        return array_map(
            fn (string $kept): string => self::widthMm($kept, $points, $bold) > $widthMm ? self::ellipsis($kept, $points, $widthMm, $bold) : $kept,
            $kept,
        );
    }

    private static function drawHeader(GdImage $image): void
    {
        self::rectangle($image, 0, 0, self::WIDTH_MM, 12, self::INDIGO);

        $logo = imagecreatefrompng(public_path('images/logo-unud-card.png'));
        imagecopyresampled(
            $image, $logo, self::px(3.5), self::px(1.75), 0, 0,
            self::px(8.5), self::px(8.5), imagesx($logo), imagesy($logo),
        );

        self::write($image, 'UNIVERSITAS UDAYANA', 14, 2, 8, 10, self::WHITE, bold: true);
        self::write($image, 'Kartu Tanda Mahasiswa Asing · International Student Card', 14, 6, 5.6, 7, self::WHITE);
    }

    private static function drawPhoto(GdImage $image, Student $student): void
    {
        $photo = self::photo($student);

        if ($photo !== null) {
            imagecopy($image, $photo, self::px(3.5), self::px(15), 0, 0, self::PHOTO_WIDTH, self::PHOTO_HEIGHT);

            return;
        }

        self::rectangle($image, 3.5, 15, 24.5, 43, self::TINT);

        $initials = self::initials($student->full_name);
        $left = 3.5 + (21 - self::widthMm($initials, 16, true)) / 2;

        self::write($image, $initials, $left, 25.5, 16, 20, self::INDIGO, bold: true);
    }

    private static function drawDetails(GdImage $image, StudentCard $card): void
    {
        $student = $card->student;
        $y = 14.2;

        [$name, $size, $line] = self::fitName($student->full_name);

        foreach ($name as $text) {
            self::write($image, $text, self::BODY_LEFT, $y, $size, $line, self::INK, bold: true);
            $y += $line * self::PT_TO_MM;
        }

        $y += 0.3;
        self::write($image, $card->card_number, self::BODY_LEFT, $y, 6.4, 8, self::INDIGO, bold: true);
        $y += 8 * self::PT_TO_MM;

        foreach ([
            'Program' => (string) $student->program?->name,
            'Fakultas / Faculty' => (string) $student->program?->faculty?->name,
        ] as $label => $value) {
            $y += 1;
            self::write($image, $label, self::BODY_LEFT, $y, 4.8, 6, self::MUTED);
            $y += 6 * self::PT_TO_MM;

            foreach (self::fit($value, 6.2, self::BODY_WIDTH, 2) as $text) {
                self::write($image, $text, self::BODY_LEFT, $y, 6.2, 7.5, self::INK);
                $y += 7.5 * self::PT_TO_MM;
            }
        }

        $second = self::BODY_LEFT + self::BODY_WIDTH * 0.56;
        $firstWidth = self::BODY_WIDTH * 0.54;
        $nationality = (string) $student->nationality?->name_en;
        $nationalitySize = self::widthMm($nationality, 6.2) > $firstWidth ? 5.2 : 6.2;

        self::write($image, 'Kewarganegaraan / Nationality', self::BODY_LEFT, 43.5, 4.8, 6, self::MUTED);
        self::write($image, self::fit($nationality, $nationalitySize, $firstWidth, 1)[0] ?? '', self::BODY_LEFT, 45.6, $nationalitySize, 7.5, self::INK);
        self::write($image, 'Berlaku sampai / Valid until', $second, 43.5, 4.8, 6, self::MUTED);
        self::write($image, $card->valid_until->locale('en')->translatedFormat('j F Y'), $second, 45.6, 6.2, 7.5, self::INK);
    }

    /**
     * Ukuran terbesar yang memuat nama utuh dalam dua baris; nama yang lebih panjang lagi dipotong.
     *
     * @return array{0: list<string>, 1: float, 2: float}
     */
    private static function fitName(string $name): array
    {
        $full = trim((string) preg_replace('/\s+/u', ' ', $name));

        foreach ([[9.5, 11.0], [7.5, 9.0], [6.5, 8.0]] as [$size, $line]) {
            $lines = self::fit($name, $size, self::BODY_WIDTH, 2, true);

            if (implode(' ', $lines) === $full) {
                return [$lines, $size, $line];
            }
        }

        return [$lines, $size, $line];
    }

    private static function drawWatermark(GdImage $image, StudentCard $card): void
    {
        $text = self::watermark($card);

        if ($text === null) {
            return;
        }

        $size = self::fontSize(15);
        $font = self::font($text, true);
        $box = imagettfbbox($size, 0, $font, $text);
        $width = $box[2] - $box[0];
        $angle = deg2rad(16);

        imagettftext(
            $image, $size, 16,
            (int) (self::WIDTH / 2 - cos($angle) * $width / 2),
            (int) (self::HEIGHT / 2 + sin($angle) * $width / 2 + 20),
            imagecolorallocatealpha($image, ...[...self::RED, 83]),
            $font,
            $text,
        );
    }

    /**
     * Potongan tengah 3×4 di atas latar putih (PNG transparan tidak menjadi hitam).
     */
    private static function cropToCard(GdImage $source, int $width, int $height): GdImage
    {
        $cropWidth = (int) min($width, round($height * self::PHOTO_WIDTH / self::PHOTO_HEIGHT));
        $cropHeight = (int) min($height, round($width * self::PHOTO_HEIGHT / self::PHOTO_WIDTH));

        $target = imagecreatetruecolor(self::PHOTO_WIDTH, self::PHOTO_HEIGHT);
        imagefill($target, 0, 0, self::color($target, self::WHITE));
        imagecopyresampled(
            $target, $source, 0, 0,
            (int) (($width - $cropWidth) / 2), (int) (($height - $cropHeight) / 2),
            self::PHOTO_WIDTH, self::PHOTO_HEIGHT, $cropWidth, $cropHeight,
        );

        return $target;
    }

    /**
     * @param  array{int, int, int}  $color
     */
    private static function write(GdImage $image, string $text, float $left, float $top, float $points, float $line, array $color, bool $bold = false): void
    {
        if ($text === '') {
            return;
        }

        // Garis dasar: huruf besar berada di tengah tinggi barisnya
        $baseline = self::px($top) + ($line + $points * 0.7) / 2 * self::DPI / 72;

        imagettftext($image, self::fontSize($points), 0, self::px($left), (int) $baseline, self::color($image, $color), self::font($text, $bold), $text);
    }

    /**
     * @param  array{int, int, int}  $color
     */
    private static function rectangle(GdImage $image, float $left, float $top, float $right, float $bottom, array $color): void
    {
        imagefilledrectangle($image, self::px($left), self::px($top), self::px($right) - 1, self::px($bottom) - 1, self::color($image, $color));
    }

    private static function ellipsis(string $text, float $points, float $widthMm, bool $bold): string
    {
        while ($text !== '' && self::widthMm($text.'…', $points, $bold) > $widthMm) {
            $text = rtrim(mb_substr($text, 0, -1));
        }

        return $text.'…';
    }

    private static function widthMm(string $text, float $points, bool $bold = false): float
    {
        $box = imagettfbbox(self::fontSize($points), 0, self::font($text, $bold), $text);

        return ($box[2] - $box[0]) / self::DPI * 25.4;
    }

    /**
     * GD menghitung ukuran font pada 96 DPI, sedangkan kartu digambar pada 300 DPI.
     */
    private static function fontSize(float $points): float
    {
        return $points * self::DPI / 96;
    }

    /**
     * Berkas font untuk sebuah teks. Publik agar pemilihan font cadangan bisa diuji.
     */
    public static function font(string $text, bool $bold = false): string
    {
        return preg_match(self::DM_SANS_CHARACTERS, $text) === 1
            ? resource_path('fonts/DMSans-'.($bold ? 'Bold' : 'Regular').'.ttf')
            : resource_path('fonts/DejaVuSans'.($bold ? '-Bold' : '').'.ttf');
    }

    private static function px(float $mm): int
    {
        return (int) round($mm * self::DPI / 25.4);
    }

    /**
     * @param  array{int, int, int}  $rgb
     */
    private static function color(GdImage $image, array $rgb): int
    {
        return (int) imagecolorallocate($image, ...$rgb);
    }

    private static function safeNumber(StudentCard $card): string
    {
        return trim((string) preg_replace('/[^A-Za-z0-9]+/', '-', $card->card_number), '-');
    }
}
