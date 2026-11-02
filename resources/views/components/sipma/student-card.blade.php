{{-- Kartu KTM, dipakai pratinjau layar dan PDF. Hanya CSS yang didukung dompdf: posisi absolut, tabel,
     satuan mm/pt, tanpa flex/grid. Teksnya dua bahasa dan tetap, tidak mengikuti bahasa antarmuka. --}}
@props(['card'])

@php
    use App\Enums\StudentCardStatus;
    use App\Support\StudentCardDocument;
    use Illuminate\Support\Str;

    $card->loadMissing(['student.program.faculty', 'student.nationality']);
    $student = $card->student;
    $photo = StudentCardDocument::photo($student);

    // Kartu tidak bisa memanjang: dompdf memindahkan teks yang kelewat panjang ke halaman kedua
    $name = Str::limit($student->full_name, 110);
    $program = Str::limit((string) $student->program?->name, 96);
    $faculty = Str::limit((string) $student->program?->faculty?->name, 96);
    $nationality = Str::limit((string) $student->nationality?->name_en, 44);

    [$nameSize, $nameLine] = match (true) {
        mb_strlen($name) > 56 => ['6.5pt', '8pt'],
        mb_strlen($name) > 30 => ['7.5pt', '9pt'],
        default => ['9.5pt', '11pt'],
    };
    $watermark = match ($card->status) {
        StudentCardStatus::Revoked => 'DICABUT / REVOKED',
        StudentCardStatus::Expired => 'KEDALUWARSA / EXPIRED',
        default => null,
    };
@endphp

<style>
    .ktm { position: relative; width: 85.6mm; height: 54mm; overflow: hidden; background-color: #FFFFFF; color: #20293A; font-family: 'DM Sans', 'DejaVu Sans', sans-serif; text-align: left; }
    .ktm-head { position: absolute; top: 0; left: 0; width: 85.6mm; height: 12mm; background-color: #4B4DD6; }
    .ktm-logo { position: absolute; top: 1.75mm; left: 3.5mm; width: 8.5mm; height: 8.5mm; }
    .ktm-org { position: absolute; top: 2mm; left: 14mm; width: 69mm; color: #FFFFFF; font-size: 8pt; font-weight: bold; line-height: 10pt; }
    .ktm-title { position: absolute; top: 6mm; left: 14mm; width: 69mm; color: #FFFFFF; font-size: 5.6pt; line-height: 7pt; }
    .ktm-photo { position: absolute; top: 15mm; left: 3.5mm; width: 21mm; height: 28mm; }
    .ktm-initials { position: absolute; top: 15mm; left: 3.5mm; width: 21mm; height: 28mm; background-color: #EEEEFD; }
    .ktm-initials-text { position: absolute; top: 10.5mm; left: 0; width: 21mm; color: #4B4DD6; font-size: 16pt; font-weight: bold; line-height: 20pt; text-align: center; }
    .ktm-body { position: absolute; top: 14.2mm; left: 27mm; width: 55.5mm; height: 29mm; overflow: hidden; }
    .ktm-name { font-weight: bold; }
    .ktm-number { margin-top: 0.3mm; color: #4B4DD6; font-family: 'DejaVu Sans Mono', 'DM Mono', monospace; font-size: 6.4pt; font-weight: bold; line-height: 8pt; }
    .ktm-label { margin-top: 1mm; color: #6D727F; font-size: 4.8pt; line-height: 6pt; }
    .ktm-value { font-size: 6.2pt; line-height: 7.5pt; }
    .ktm-foot { position: absolute; top: 43.5mm; left: 27mm; width: 55.5mm; border-collapse: collapse; }
    .ktm-foot td { padding: 0; vertical-align: top; }
    .ktm-foot .ktm-label { margin-top: 0; }
    .ktm-strip { position: absolute; top: 52.5mm; left: 0; width: 85.6mm; height: 1.5mm; background-color: #4B4DD6; }
    .ktm-mark { position: absolute; top: 24mm; left: -5mm; width: 95.6mm; color: #C02F2F; font-size: 15pt; font-weight: bold; text-align: center; opacity: 0.35; transform: rotate(-16deg); }
</style>

<div {{ $attributes->class('ktm') }}>
    <div class="ktm-head"></div>
    <img class="ktm-logo" src="{{ StudentCardDocument::logo() }}" alt="">
    <div class="ktm-org">UNIVERSITAS UDAYANA</div>
    <div class="ktm-title">Kartu Tanda Mahasiswa Asing · International Student Card</div>

    @if ($photo)
        <img class="ktm-photo" src="{{ $photo }}" alt="">
    @else
        <div class="ktm-initials"><div class="ktm-initials-text">{{ StudentCardDocument::initials($student->full_name) }}</div></div>
    @endif

    <div class="ktm-body">
        <div class="ktm-name" style="font-size: {{ $nameSize }}; line-height: {{ $nameLine }}">{{ $name }}</div>
        <div class="ktm-number">{{ $card->card_number }}</div>
        <div class="ktm-label">Program</div>
        <div class="ktm-value">{{ $program }}</div>
        <div class="ktm-label">Fakultas / Faculty</div>
        <div class="ktm-value">{{ $faculty }}</div>
    </div>

    <table class="ktm-foot">
        <tr>
            <td style="width: 56%">
                <div class="ktm-label">Kewarganegaraan / Nationality</div>
                <div class="ktm-value" @if (mb_strlen($nationality) > 24) style="font-size: 5.2pt; line-height: 6.5pt" @endif>{{ $nationality }}</div>
            </td>
            <td style="width: 44%">
                <div class="ktm-label">Berlaku sampai / Valid until</div>
                <div class="ktm-value">{{ $card->valid_until->locale('en')->translatedFormat('j F Y') }}</div>
            </td>
        </tr>
    </table>

    <div class="ktm-strip"></div>

    @if ($watermark)
        <div class="ktm-mark">{{ $watermark }}</div>
    @endif
</div>
