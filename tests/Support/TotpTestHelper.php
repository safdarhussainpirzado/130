<?php

namespace Tests\Support;

/**
 * Pure PHP RFC 6238 TOTP generator for automated testing.
 * Enables deterministic 2FA authentication in automated unit, feature, and browser test suites
 * without requiring external third-party TOTP packages.
 */
class TotpTestHelper
{
    /**
     * Generate a valid 6-digit TOTP code for a given Base32 secret at the given timestamp.
     */
    public static function generate(string $secret, ?int $timestamp = null): string
    {
        $timestamp = $timestamp ?? time();
        $cleaned = strtoupper(str_replace(' ', '', trim($secret)));
        $padLen = (8 - (strlen($cleaned) % 8)) % 8;
        $cleaned .= str_repeat('=', $padLen);

        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $binary = '';
        foreach (str_split(rtrim($cleaned, '=')) as $char) {
            $val = strpos($alphabet, $char);
            if ($val !== false) {
                $binary .= str_pad(decbin($val), 5, '0', STR_PAD_LEFT);
            }
        }

        $bytes = '';
        foreach (str_split($binary, 8) as $byte) {
            if (strlen($byte) === 8) {
                $bytes .= chr(bindec($byte));
            }
        }

        $timeCounter = pack('N*', 0).pack('N*', intdiv($timestamp, 30));
        $hash = hash_hmac('sha1', $timeCounter, $bytes, true);
        $offset = ord(substr($hash, -1)) & 0x0F;
        $part = substr($hash, $offset, 4);
        $value = unpack('N', $part)[1] & 0x7FFFFFFF;
        $code = $value % 1000000;

        return str_pad((string) $code, 6, '0', STR_PAD_LEFT);
    }
}
