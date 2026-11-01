<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Sesi login aktif (Profil saya → Keamanan), dibaca dari tabel sessions (SESSION_DRIVER=database).
 *
 * Menghapus baris sesi saja tidak cukup: perangkat dengan "Ingat saya" akan masuk lagi lewat
 * cookie recaller. Karena itu token remember diganti setiap kali sesi lain dikeluarkan, lalu
 * cookie perangkat ini diterbitkan ulang dengan token baru agar perangkat ini tetap diingat.
 */
final class UserSessions
{
    /**
     * @return Collection<int, array{id: string, browser: string, platform: string, device: string, ip: ?string, last_active: Carbon, current: bool}>
     */
    public static function for(User $user): Collection
    {
        if (config('session.driver') !== 'database') {
            return collect();
        }

        $current = session()->getId();
        $since = now()->subMinutes((int) config('session.lifetime'))->getTimestamp();

        return DB::table(config('session.table', 'sessions'))
            ->where('user_id', $user->getKey())
            ->where('last_activity', '>=', $since)
            ->orderByDesc('last_activity')
            ->get()
            ->map(fn (object $row): array => [
                'id' => $row->id,
                ...self::describeAgent((string) $row->user_agent),
                'ip' => $row->ip_address,
                'last_active' => Carbon::createFromTimestamp($row->last_activity),
                'current' => $row->id === $current,
            ])
            ->sortByDesc('current')
            ->values();
    }

    public static function revoke(User $user, string $sessionId): void
    {
        abort_if($sessionId === session()->getId(), 422);

        DB::table(config('session.table', 'sessions'))
            ->where('user_id', $user->getKey())
            ->where('id', $sessionId)
            ->delete();

        self::rotateRememberToken($user);
    }

    public static function revokeOthers(User $user): void
    {
        DB::table(config('session.table', 'sessions'))
            ->where('user_id', $user->getKey())
            ->where('id', '!=', session()->getId())
            ->delete();

        self::rotateRememberToken($user);
    }

    /**
     * Nama browser, sistem operasi, dan jenis perangkat dari User-Agent (tanpa layanan luar).
     *
     * @return array{browser: string, platform: string, device: string}
     */
    public static function describeAgent(string $agent): array
    {
        $browser = match (true) {
            str_contains($agent, 'Edg/') => 'Edge',
            str_contains($agent, 'OPR/') || str_contains($agent, 'Opera') => 'Opera',
            str_contains($agent, 'Firefox/') || str_contains($agent, 'FxiOS') => 'Firefox',
            str_contains($agent, 'Chrome/') || str_contains($agent, 'CriOS') => 'Chrome',
            str_contains($agent, 'Safari/') => 'Safari',
            default => __('profile.security.unknown_browser'),
        };

        $platform = match (true) {
            str_contains($agent, 'iPhone') => 'iPhone',
            str_contains($agent, 'iPad') => 'iPad',
            str_contains($agent, 'Android') => 'Android',
            str_contains($agent, 'Windows') => 'Windows',
            str_contains($agent, 'Mac OS X') || str_contains($agent, 'Macintosh') => 'macOS',
            str_contains($agent, 'CrOS') => 'ChromeOS',
            str_contains($agent, 'Linux') => 'Linux',
            default => __('profile.security.unknown_platform'),
        };

        $device = match (true) {
            str_contains($agent, 'iPad') || (str_contains($agent, 'Android') && ! str_contains($agent, 'Mobile')) => 'tablet',
            str_contains($agent, 'Mobile') || str_contains($agent, 'iPhone') => 'mobile',
            default => 'desktop',
        };

        return ['browser' => $browser, 'platform' => $platform, 'device' => $device];
    }

    private static function rotateRememberToken(User $user): void
    {
        $guard = Auth::guard();
        $remembered = request()->cookies->has($guard->getRecallerName());

        $user->setRememberToken(Str::random(60));
        $user->timestamps = false;
        $user->save();
        $user->timestamps = true;

        if ($remembered) {
            $guard->getCookieJar()->queue($guard->getCookieJar()->make(
                $guard->getRecallerName(),
                $user->getAuthIdentifier().'|'.$user->getRememberToken().'|'.$user->getAuthPassword(),
                576000, // sama dengan bawaan SessionGuard (5 tahun)
            ));
        }
    }
}
