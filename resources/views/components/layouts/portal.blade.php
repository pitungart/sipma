<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ isset($title) ? $title.' · ' : '' }}SIPMA · Universitas Udayana</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600;9..40,700&display=swap">

    @vite('resources/css/app.css')
</head>
<body class="min-h-screen bg-card font-sans text-body text-ink antialiased">
    {{-- Target #main disediakan oleh tiap halaman --}}
    <a href="#main" class="sipma-skip-link">{{ __('portal.skip_to_content') }}</a>

    {{ $slot }}
</body>
</html>
