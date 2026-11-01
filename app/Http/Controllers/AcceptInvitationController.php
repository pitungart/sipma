<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\StudentAccounts;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;

/**
 * Undangan akun mahasiswa → halaman "buat password" Filament. Token reset dibuat saat tautan
 * dibuka (berlaku 60 menit), sehingga undangan bisa berlaku lebih lama tanpa memperpanjang
 * masa berlaku reset password biasa.
 */
class AcceptInvitationController extends Controller
{
    public function __invoke(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->hasRole(UserRole::Student) && $user->is_active, 404);

        $panel = Filament::getPanel('app');

        // Kedaluwarsa atau diubah: mahasiswa meminta tautan baru lewat "Lupa password"
        if (! $request->hasValidSignature()) {
            return redirect($panel->getRequestPasswordResetUrl());
        }

        // Password sudah dibuat: undangan tidak berlaku lagi
        if (! hash_equals(StudentAccounts::fingerprint($user), (string) $request->query('h'))) {
            return redirect()->route('login');
        }

        if (Auth::check() && ! Auth::user()->is($user)) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        $token = Password::broker($panel->getAuthPasswordBroker())->createToken($user);

        return redirect($panel->getResetPasswordUrl($token, $user));
    }
}
