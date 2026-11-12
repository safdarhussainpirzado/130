<?php

namespace App\Services\Geo;

use App\Models\LoginLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Cookie as SymfonyCookie;

class DeviceFingerprintService
{
    public const COOKIE_NAME = 'nhmp_device_sig';
    public const COOKIE_LIFETIME_DAYS = 365;

    /**
     * Resolve or generate a privacy-preserving device token backed by a signed cookie.
     *
     * @param Request $request
     * @return array{token: string, cookie: ?SymfonyCookie}
     */
    public function resolveDeviceToken(Request $request): array
    {
        $rawCookie = (string) $request->cookie(self::COOKIE_NAME, '');
        $appKey = (string) config('app.key', 'NHMP_130_DEFAULT_SECRET_KEY');

        if (!empty($rawCookie) && str_contains($rawCookie, '.')) {
            [$token, $sig] = explode('.', $rawCookie, 2);
            $expectedSig = hash_hmac('sha256', $token, $appKey);
            if (hash_equals($expectedSig, $sig) && strlen($token) >= 16) {
                return ['token' => $token, 'cookie' => null];
            }
        }

        // Generate fresh unguessable token
        $newToken = Str::random(32);
        $sig = hash_hmac('sha256', $newToken, $appKey);
        $cookieValue = $newToken . '.' . $sig;

        $cookie = Cookie::make(
            self::COOKIE_NAME,
            $cookieValue,
            60 * 24 * self::COOKIE_LIFETIME_DAYS,
            '/',
            null,
            $request->isSecure(),
            true, // HttpOnly
            false,
            'Lax'
        );

        return ['token' => $newToken, 'cookie' => $cookie];
    }

    /**
     * Compute a deterministic, privacy-preserving device hash.
     */
    public function computeDeviceHash(int $userId, ?string $userAgent, string $deviceToken): string
    {
        $appKey = (string) config('app.key', 'NHMP_130_DEFAULT_SECRET_KEY');
        $ua = $userAgent ?? 'unknown';

        return hash_hmac('sha256', "{$userId}|{$ua}|{$deviceToken}", $appKey);
    }

    /**
     * Detect anomaly signals: new device, new country, and impossible travel.
     *
     * @param User $user
     * @param array $geoData Output of GeoLookup::lookup()
     * @param string $deviceHash
     * @return array<int, string>
     */
    public function detectAnomalies(User $user, array $geoData, string $deviceHash): array
    {
        $alerts = [];
        $currentCountry = $geoData['country'] ?? null;

        // Fetch recent login history for this user
        $recentLogins = LoginLog::where('user_id', $user->id)
            ->orderBy('id', 'desc')
            ->limit(20)
            ->get();

        if ($recentLogins->isEmpty()) {
            // First time login for this account
            return $alerts;
        }

        // 1. New device detection
        if (config('geo_access.alert_new_device', true)) {
            $knownDevices = $recentLogins->pluck('device_hash')->filter()->unique();
            if ($knownDevices->isNotEmpty() && !$knownDevices->contains($deviceHash)) {
                $alerts[] = 'new_device_or_browser';
            }
        }

        // 2. New country & impossible travel detection
        if ($currentCountry !== null) {
            $priorCountryLogins = $recentLogins->filter(fn ($l) => !empty($l->country));

            if ($priorCountryLogins->isNotEmpty()) {
                $lastCountryLogin = $priorCountryLogins->first();
                $prevCountry = (string) $lastCountryLogin->country;

                if ($prevCountry !== $currentCountry) {
                    if (config('geo_access.alert_new_country', true)) {
                        $alerts[] = "new_country_for_user:{$currentCountry}";
                    }

                    // Impossible travel check
                    if (config('geo_access.impossible_travel.enabled', true)) {
                        $minHours = (float) config('geo_access.impossible_travel.min_hours_between_countries', 3.0);
                        $loginTime = $lastCountryLogin->login_at ? Carbon::parse($lastCountryLogin->login_at) : null;

                        if ($loginTime) {
                            $hoursElapsed = abs(now()->diffInSeconds($loginTime, false)) / 3600;
                            if ($hoursElapsed < $minHours) {
                                $elapsedFormatted = round($hoursElapsed, 1);
                                $alerts[] = "impossible_travel:{$prevCountry}->{$currentCountry}:{$elapsedFormatted}h";
                            }
                        }
                    }
                }
            }
        }

        return $alerts;
    }
}
