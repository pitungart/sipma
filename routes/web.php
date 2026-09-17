<?php

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
