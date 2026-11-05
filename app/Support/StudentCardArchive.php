<?php

namespace App\Support;

use App\Models\StudentCard;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

/**
 * ZIP berisi KTM (JPG) untuk diunduh sekaligus. Berkasnya sementara dan dihapus setelah terkirim,
 * jadi tidak menumpuk di disk. Hak unduh diperiksa lagi per kartu oleh controller.
 */
final class StudentCardArchive
{
    private const SESSION_KEY = 'student_card_archives';

    /**
     * ZipArchive menahan isi berkas di memori sampai objeknya dilepas (menutupnya saja tidak cukup),
     * jadi objeknya diganti berkala.
     */
    private const FLUSH_EVERY = 50;

    /**
     * Tautan unduh sekali pakai. Daftar KTM disimpan di session karena bisa terlalu panjang untuk URL.
     *
     * @param  array<int, string>  $cardIds
     */
    public static function prepare(array $cardIds, string $filename): string
    {
        $token = Str::random(32);

        session()->put(self::SESSION_KEY.'.'.$token, ['ids' => array_values($cardIds), 'filename' => $filename]);

        return route('files.student-card-archive', ['token' => $token]);
    }

    /**
     * @return array{ids: list<string>, filename: string}|null
     */
    public static function pull(string $token): ?array
    {
        return session()->pull(self::SESSION_KEY.'.'.$token);
    }

    public static function periodFilename(string $period): string
    {
        return "KTM-{$period}.zip";
    }

    public static function selectionFilename(): string
    {
        return 'KTM-'.now()->format('Y-m-d-His').'.zip';
    }

    /**
     * Kartu dibaca satu per satu: ribuan kartu tidak boleh dimuat sekaligus ke memori.
     *
     * @param  iterable<int, StudentCard>  $cards
     * @return string path berkas sementara
     */
    public static function build(iterable $cards): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'sipma-ktm-');

        try {
            $zip = self::open($path, ZipArchive::OVERWRITE);
            $count = 0;

            foreach ($cards as $card) {
                $entry = StudentCardDocument::archiveEntry($card);

                $zip->addFromString($entry, StudentCardDocument::jpeg($card));
                // JPG sudah terkompresi; dikompres lagi hanya menambah waktu
                $zip->setCompressionName($entry, ZipArchive::CM_STORE);

                if (++$count % self::FLUSH_EVERY === 0) {
                    $zip->close();
                    $zip = self::open($path);
                }
            }

            $zip->close();
        } catch (\Throwable $e) {
            @unlink($path);

            throw $e;
        }

        return $path;
    }

    private static function open(string $path, int $flags = 0): ZipArchive
    {
        $zip = new ZipArchive;

        if ($zip->open($path, $flags) !== true) {
            throw new RuntimeException('ZIP KTM tidak bisa dibuat.');
        }

        return $zip;
    }
}
