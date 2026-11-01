<?php

namespace App\Support;

use App\Enums\AccountProvision;
use App\Enums\UserRole;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Akun portal untuk mahasiswa yang didaftarkan agen (keputusan 1 November 2026): dibuat saat
 * ditandai Aktif agar mahasiswa bisa membuka KTM & rubrik. Agen tetap memegang datanya.
 */
final class StudentAccounts
{
    public static function provision(Student $student): AccountProvision
    {
        if ($student->user_id !== null) {
            return AccountProvision::Existing;
        }

        $email = Str::lower(trim((string) $student->email));

        if ($email === '') {
            return AccountProvision::MissingEmail;
        }

        $user = User::query()->where('email', $email)->first();

        if ($user !== null) {
            if (! $user->hasRole(UserRole::Student) || $user->student()->exists()) {
                return AccountProvision::EmailTaken;
            }

            $student->update(['user_id' => $user->getKey()]);

            return AccountProvision::Linked;
        }

        $user = User::query()->create([
            'name' => $student->full_name,
            'email' => $email,
            'password' => Str::password(40), // tidak pernah dikirim; mahasiswa membuat sendiri lewat undangan
            'role' => UserRole::Student,
            'locale' => 'en',
            'is_active' => true,
        ]);
        // Email berasal dari data pendaftaran yang diverifikasi KUI, dan undangan hanya sampai ke email itu
        $user->forceFill(['email_verified_at' => now()])->save();

        $student->update(['user_id' => $user->getKey()]);

        return AccountProvision::Created;
    }

    /**
     * Tautan undangan bertanda tangan; berlaku config('sipma.invitation_days') hari.
     */
    public static function invitationUrl(User $user): string
    {
        return URL::temporarySignedRoute(
            'invitation.accept',
            now()->addDays((int) config('sipma.invitation_days')),
            ['user' => $user, 'h' => self::fingerprint($user)],
        );
    }

    /**
     * Berubah begitu password diganti, sehingga undangan hanya bisa dipakai sekali.
     */
    public static function fingerprint(User $user): string
    {
        return substr(hash_hmac('sha256', (string) $user->getAuthPassword(), (string) config('app.key')), 0, 24);
    }
}
