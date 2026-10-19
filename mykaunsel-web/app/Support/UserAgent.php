<?php

namespace App\Support;

/**
 * Tiny user-agent reader for the "Active sessions" list. Only needs a friendly
 * "Chrome on Windows" label — not a full device-detection library.
 */
class UserAgent
{
    /** @return array{browser: string, os: string, mobile: bool, label: string} */
    public static function parse(?string $agent): array
    {
        $ua = (string) $agent;

        $browser = match (true) {
            str_contains($ua, 'Edg/') => 'Edge',
            str_contains($ua, 'OPR/'), str_contains($ua, 'Opera') => 'Opera',
            str_contains($ua, 'SamsungBrowser') => 'Samsung Internet',
            str_contains($ua, 'Firefox/'), str_contains($ua, 'FxiOS') => 'Firefox',
            str_contains($ua, 'Chrome/'), str_contains($ua, 'CriOS') => 'Chrome',
            str_contains($ua, 'Safari/') => 'Safari',
            default => 'Unknown browser',
        };

        // Order matters: iPhone UAs contain "Mac OS X", Android UAs contain "Linux".
        $os = match (true) {
            str_contains($ua, 'Windows') => 'Windows',
            str_contains($ua, 'iPhone'), str_contains($ua, 'iPad') => 'iOS',
            str_contains($ua, 'Android') => 'Android',
            str_contains($ua, 'Macintosh'), str_contains($ua, 'Mac OS X') => 'macOS',
            str_contains($ua, 'CrOS') => 'ChromeOS',
            str_contains($ua, 'Linux') => 'Linux',
            default => 'Unknown OS',
        };

        return [
            'browser' => $browser,
            'os' => $os,
            'mobile' => (bool) preg_match('/Mobile|iPhone|iPad|Android/i', $ua),
            'label' => "{$browser} on {$os}",
        ];
    }
}
