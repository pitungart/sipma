<?php

namespace App\Http\Controllers;

use App\Enums\LoaStatus;
use App\Models\Document;
use App\Models\Loa;
use App\Models\Mou;
use App\Models\Payment;
use App\Models\Student;
use App\Models\StudentCard;
use App\Models\TeachingDecree;
use App\Models\User;
use App\Support\FileTemplates;
use App\Support\PrivateFiles;
use App\Support\StudentCardArchive;
use App\Support\StudentCardDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Satu pintu untuk membuka berkas privat. Setiap berkas diperiksa Policy pemiliknya;
 * Super Admin lolos lewat Gate::before. Tanpa `?download=1` berkas dibuka di browser (pratinjau).
 * Template berkas tidak memakai Policy: isinya bukan data pribadi, cukup wajib login.
 */
class PrivateFileController extends Controller
{
    public function document(Request $request, Document $document): StreamedResponse
    {
        Gate::authorize('view', $document);

        return $this->respond($request, $document->file_path, $document->original_name);
    }

    public function payment(Request $request, Payment $payment): StreamedResponse
    {
        Gate::authorize('view', $payment);

        return $this->respond($request, $payment->proof_file, 'payment-'.$payment->type->value);
    }

    public function mou(Request $request, Mou $mou): StreamedResponse
    {
        Gate::authorize('view', $mou);

        return $this->respond($request, $mou->file_path, 'mou');
    }

    /**
     * UC-02: unduhan pertama oleh pemilik dicatat (status "sudah diunduh"); unduhan staf tidak.
     */
    public function loa(Request $request, Loa $loa): StreamedResponse
    {
        Gate::authorize('download', $loa);

        if ($loa->student->isOwnedBy($request->user()) && $loa->downloaded_at === null) {
            $loa->update(['status' => LoaStatus::Downloaded, 'downloaded_at' => now()]);
        }

        return $this->respond($request, $loa->file_path, 'LOA-'.Str::slug($loa->student->full_name), forceDownload: true);
    }

    /**
     * KTM dibuat dari data saat diminta (tidak disimpan sebagai berkas), jadi selalu mengikuti
     * status terbaru. `?download=1` mengunduh; tanpa itu PDF dibuka di browser untuk dicetak.
     */
    public function studentCard(Request $request, StudentCard $card): Response
    {
        Gate::authorize('download', $card);

        return response(StudentCardDocument::jpeg($card), 200, [
            'Content-Type' => 'image/jpeg',
            'Content-Disposition' => ($request->boolean('download') ? 'attachment' : 'inline').'; filename="'.StudentCardDocument::filename($card).'"',
        ]);
    }

    /**
     * Pas Foto yang disetujui, dipotong 3×4. Siapa pun yang boleh melihat mahasiswanya boleh melihat
     * fotonya: foto yang sama sudah tercetak di KTM.
     */
    public function studentPhoto(Student $student): Response
    {
        Gate::authorize('view', $student);

        $photo = StudentCardDocument::photoJpeg($student);

        abort_if($photo === null, 404);

        return response($photo, 200, ['Content-Type' => 'image/jpeg']);
    }

    /**
     * ZIP KTM dari tautan sekali pakai yang disiapkan aksi massal. Hak unduh diperiksa lagi per kartu.
     */
    public function studentCardArchive(string $token): BinaryFileResponse
    {
        $archive = StudentCardArchive::pull($token);

        abort_if($archive === null, 404);

        $cards = StudentCard::query()
            ->with(['student.program.faculty', 'student.nationality'])
            ->whereKey($archive['ids'])
            ->lazyById()
            ->filter(fn (StudentCard $card): bool => Gate::allows('download', $card));

        abort_if($cards->isEmpty(), 403);

        // Ratusan kartu bisa melewati batas waktu bawaan PHP
        set_time_limit(0);

        return response()->download(StudentCardArchive::build($cards), $archive['filename'], ['Content-Type' => 'application/zip'])->deleteFileAfterSend();
    }

    /**
     * Berkas SK mengajar: Super Admin dan Admin Fakultas pemilik SK, juga saat SK-nya sudah dihapus.
     */
    public function teachingDecree(Request $request, TeachingDecree $decree): StreamedResponse
    {
        Gate::authorize('view', $decree);

        return $this->respond($request, $decree->document, 'SK-'.$decree->decree_number);
    }

    /**
     * Template berkas (Sistem → Unggahan berkas): semua pengguna yang login boleh mengunduh;
     * MOU tanpa template unggahan memakai berkas bawaan.
     */
    public function template(Request $request, string $name): StreamedResponse|BinaryFileResponse
    {
        abort_unless(FileTemplates::isValidName($name), 404);

        $template = FileTemplates::find($name);

        if ($template === null && $name === FileTemplates::MOU) {
            return response()->download(resource_path(FileTemplates::MOU_FALLBACK), 'MOU-template-KUI-Udayana.pdf');
        }

        abort_if($template === null, 404);

        return $this->respond($request, $template->file_path, $template->original_name, forceDownload: true);
    }

    /**
     * Foto profil: pemiliknya sendiri dan staf panel admin (daftar pengguna) boleh melihat.
     */
    public function avatar(Request $request, User $user): StreamedResponse
    {
        abort_unless($request->user()->is($user) || $request->user()->panelId() === 'admin', 403);

        return $this->respond($request, $user->avatar_path, 'avatar');
    }

    private function respond(Request $request, ?string $path, ?string $name, bool $forceDownload = false): StreamedResponse
    {
        abort_unless(PrivateFiles::exists($path), 404);

        $disk = Storage::disk(PrivateFiles::DISK);
        $filename = $this->filename($name, $path);

        return $forceDownload || $request->boolean('download')
            ? $disk->download($path, $filename)
            : $disk->response($path, $filename, ['Content-Disposition' => 'inline; filename="'.$filename.'"']);
    }

    /**
     * Nama unduhan memakai ekstensi berkas tersimpan agar tidak bisa dipalsukan lewat nama asli.
     */
    private function filename(?string $name, string $path): string
    {
        $extension = pathinfo($path, PATHINFO_EXTENSION);
        $base = Str::slug(pathinfo((string) $name, PATHINFO_FILENAME)) ?: 'file';

        return $extension ? "{$base}.{$extension}" : $base;
    }
}
