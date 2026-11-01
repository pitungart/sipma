<?php

use App\Http\Controllers\AcceptInvitationController;
use App\Http\Controllers\PrivateFileController;
use App\Http\Controllers\SwitchLocaleController;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;
use App\Support\Locale;
use Illuminate\Support\Facades\Route;

// Belum ada landing page: tamu diarahkan ke login, pengguna yang sudah masuk ke panelnya
Route::get('/', fn () => auth()->check()
    ? redirect(auth()->user()->panelUrl())
    : redirect()->route('login'))->name('home');

// UC-05: satu halaman daftar untuk mahasiswa mandiri dan agen mitra
Route::get('/register', Register::class)->name('register');

// UC-06: satu halaman login untuk semua role; diarahkan ke panel sesuai role setelah masuk
Route::get('/login', Login::class)->name('login');

// Undangan akun mahasiswa yang didaftarkan agen (dikirim saat ditandai Aktif): tanda tangan diperiksa controller
Route::get('/invitation/{user}', AcceptInvitationController::class)->name('invitation.accept');

// Ganti bahasa portal & panel agen/mahasiswa (disimpan di session + cookie)
Route::get('/locale/{locale}', SwitchLocaleController::class)
    ->whereIn('locale', Locale::SUPPORTED)
    ->name('locale.switch');

// Berkas privat (dokumen, bukti bayar, MOU, LOA, template): selalu lewat Policy atau wajib login, tidak pernah URL publik (R-4.10)
Route::middleware('auth')->prefix('files')->name('files.')->group(function (): void {
    Route::get('/documents/{document}', [PrivateFileController::class, 'document'])->name('document');
    Route::get('/payments/{payment}', [PrivateFileController::class, 'payment'])->name('payment');
    Route::get('/mous/{mou}', [PrivateFileController::class, 'mou'])->name('mou');
    Route::get('/loas/{loa}', [PrivateFileController::class, 'loa'])->name('loa');
    Route::get('/templates/{name}', [PrivateFileController::class, 'template'])->name('template');
    Route::get('/avatars/{user}', [PrivateFileController::class, 'avatar'])->name('avatar');
});

// URL yang tidak cocok dengan route mana pun tetap melewati middleware web (session, auth, bahasa),
// sehingga halaman 404 tahu bahasa pilihan & apakah pengguna sudah masuk
Route::fallback(fn () => abort(404));
