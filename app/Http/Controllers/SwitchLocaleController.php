<?php

namespace App\Http\Controllers;

use App\Support\Locale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

class SwitchLocaleController extends Controller
{
    /**
     * Simpan pilihan bahasa (session + cookie) lalu kembali ke halaman sebelumnya.
     * Nilai locale sudah dibatasi oleh whereIn di route.
     */
    public function __invoke(Request $request, string $locale): RedirectResponse
    {
        $request->session()->put(Locale::SESSION_KEY, $locale);
        Cookie::queue(Locale::COOKIE, $locale, Locale::COOKIE_MINUTES);

        // Tanpa Referer/riwayat session, previous() jatuh ke "/" (halaman bawaan Laravel) — arahkan ke register.
        $previous = url()->previous(route('register'));

        // Hanya kembali ke host sendiri, agar Referer luar tidak menjadi open redirect.
        $target = parse_url($previous, PHP_URL_HOST) === $request->getHost()
            ? $previous
            : route('register');

        return redirect()->to($target);
    }
}
