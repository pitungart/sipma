<?php

use App\Http\Controllers\PrivateFileController;
use App\Http\Controllers\SwitchLocaleController;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;
use App\Support\Locale;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// UC-05: satu halaman daftar untuk mahasiswa mandiri dan agen mitra
Route::get('/register', Register::class)->name('register');

// UC-06: satu halaman login untuk semua role; diarahkan ke panel sesuai role setelah masuk
Route::get('/login', Login::class)->name('login');

// Ganti bahasa portal & panel agen/mahasiswa (disimpan di session + cookie)
Route::get('/locale/{locale}', SwitchLocaleController::class)
    ->whereIn('locale', Locale::SUPPORTED)
    ->name('locale.switch');

// Berkas privat (dokumen, bukti bayar, MOU, LOA): selalu lewat Policy, tidak pernah URL publik (R-4.10)
Route::middleware('auth')->prefix('files')->name('files.')->group(function (): void {
    Route::get('/documents/{document}', [PrivateFileController::class, 'document'])->name('document');
    Route::get('/payments/{payment}', [PrivateFileController::class, 'payment'])->name('payment');
    Route::get('/mous/{mou}', [PrivateFileController::class, 'mou'])->name('mou');
    Route::get('/loas/{loa}', [PrivateFileController::class, 'loa'])->name('loa');
});
