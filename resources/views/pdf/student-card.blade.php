{{-- PDF KTM: satu halaman seukuran kartu. Isi kartu ada di <x-sipma.student-card>. --}}
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $card->card_number }}</title>
    <style>
        @page { margin: 0; }

        @font-face {
            font-family: 'DM Sans';
            font-weight: normal;
            src: url('{{ resource_path('fonts/DMSans-Regular.ttf') }}') format('truetype');
        }

        @font-face {
            font-family: 'DM Sans';
            font-weight: bold;
            src: url('{{ resource_path('fonts/DMSans-Bold.ttf') }}') format('truetype');
        }

        html, body { margin: 0; padding: 0; }

        /* Absolut agar dompdf tidak membuka halaman kedua karena selisih pembulatan tinggi kartu */
        body .ktm { position: absolute; top: 0; left: 0; }
    </style>
</head>
<body>
    <x-sipma.student-card :card="$card" />
</body>
</html>
