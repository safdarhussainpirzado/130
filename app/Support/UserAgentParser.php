<?php

namespace App\Support;

/**
 * Tiny dependency-free User-Agent parser (browser / OS / device type) for login forensics.
 * Order matters: Edge/Opera/Samsung/etc. also contain "Chrome", Chrome also contains "Safari".
 */
class UserAgentParser
{
    /** @return array{browser: ?string, browser_version: ?string, os: ?string, os_version: ?string, device_type: string} */
    public static function parse(?string $ua): array
    {
        $ua = (string) $ua;
        $out = ['browser' => null, 'browser_version' => null, 'os' => null, 'os_version' => null, 'device_type' => 'unknown'];
        if ($ua === '') {
            return $out;
        }

        $browsers = [
            'Edge'              => '/\b(?:Edg|EdgA|EdgiOS|Edge)\/([\d.]+)/',
            'Opera'             => '/\b(?:OPR|Opera)\/([\d.]+)/',
            'Samsung Internet'  => '/SamsungBrowser\/([\d.]+)/',
            'UC Browser'        => '/UCBrowser\/([\d.]+)/',
            'Firefox'           => '/\b(?:Firefox|FxiOS)\/([\d.]+)/',
            'Chrome'            => '/\b(?:Chrome|CriOS)\/([\d.]+)/',
            'Safari'            => '/Version\/([\d.]+).*Safari\//',
            'Internet Explorer' => '/(?:MSIE |Trident\/.*rv:)([\d.]+)/',
        ];
        foreach ($browsers as $name => $re) {
            if (preg_match($re, $ua, $m)) {
                $out['browser'] = $name;
                $out['browser_version'] = explode('.', $m[1])[0];
                break;
            }
        }
        if ($out['browser'] === null && preg_match('/\b(okhttp|curl|python-requests|Postman|Dart|axios)\b/i', $ua, $m)) {
            $out['browser'] = ucfirst(strtolower($m[1]));
        }

        if (preg_match('/Windows NT ([\d.]+)/', $ua, $m)) {
            $map = ['10.0' => '10/11', '6.3' => '8.1', '6.2' => '8', '6.1' => '7'];
            $out['os'] = 'Windows';
            $out['os_version'] = $map[$m[1]] ?? $m[1];
        } elseif (preg_match('/Android ([\d.]+)/', $ua, $m)) {
            $out['os'] = 'Android';
            $out['os_version'] = explode('.', $m[1])[0];
        } elseif (preg_match('/\b(?:iPhone|iPad|iPod)\b.*?OS ([\d_]+)/', $ua, $m)) {
            $out['os'] = 'iOS';
            $out['os_version'] = explode('_', $m[1])[0];
        } elseif (preg_match('/Mac OS X ([\d_.]+)/', $ua, $m)) {
            $out['os'] = 'macOS';
            $out['os_version'] = str_replace('_', '.', implode('.', array_slice(preg_split('/[_.]/', $m[1]), 0, 2)));
        } elseif (stripos($ua, 'CrOS') !== false) {
            $out['os'] = 'ChromeOS';
        } elseif (stripos($ua, 'Linux') !== false) {
            $out['os'] = 'Linux';
        }

        if (preg_match('/iPad|Tablet|Tab\b/i', $ua)) {
            $out['device_type'] = 'tablet';
        } elseif (preg_match('/Mobi|iPhone|iPod|Android/i', $ua)) {
            $out['device_type'] = 'mobile';
        } elseif ($out['os'] !== null) {
            $out['device_type'] = 'desktop';
        } elseif ($out['browser'] !== null) {
            $out['device_type'] = 'bot_or_client';
        }

        return $out;
    }
}
