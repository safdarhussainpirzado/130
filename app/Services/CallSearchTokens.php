<?php

namespace App\Services;

use App\Models\Call;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Blind-index tokens that make the ENCRYPTED caller name and caller number searchable:
 *  - name: every word, plus its 3 and 4 character prefixes  (search "fah", "faha", "fahad", "fahad kareem")
 *  - phone: last 4/5/6/7 digits and first 6/7 digits         (search "6723497", "3497", "030067")
 * Tokens are HMAC-SHA256 (bidx_salt) truncated to 32 hex chars. Nothing readable is stored.
 */
class CallSearchTokens
{
    public static function hash(string $term): string
    {
        $salt = (function_exists('app') && app()->has('config')) ? config('app.bidx_salt') : null;
        $salt = $salt ?: docker_secret('bidx_salt', 'nhmp_130_sec_bidx_default_salt_2026');

        return substr(hash_hmac('sha256', $term, $salt), 0, 32);
    }

    /** @return array<int,string> */
    public static function nameWordTokens(string $word): array
    {
        $word = strtolower(trim($word));
        $len = mb_strlen($word);
        if ($len < 2) {
            return [];
        }
        $t = ['n:'.$word => true];
        if ($len >= 3) {
            $t['n:'.mb_substr($word, 0, 3)] = true;
        }
        if ($len >= 4) {
            $t['n:'.mb_substr($word, 0, 4)] = true;
        }

        return array_map([self::class, 'hash'], array_keys($t));
    }

    /** @return array<int,string> */
    public static function nameTokens(?string $name): array
    {
        $out = [];
        foreach (preg_split('/[\s\-_.,]+/u', strtolower(trim((string) $name)), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $w) {
            foreach (self::nameWordTokens($w) as $h) {
                $out[$h] = true;
            }
        }

        return array_keys($out);
    }

    public static function normalizePhone(?string $phone): string
    {
        $d = preg_replace('/\D+/', '', (string) $phone);
        if (str_starts_with($d, '0092')) {
            $d = '0'.substr($d, 4);
        } elseif (str_starts_with($d, '92') && strlen($d) === 12) {
            $d = '0'.substr($d, 2);
        } elseif (strlen($d) === 10 && $d[0] === '3') {
            $d = '0'.$d;
        }

        return $d;
    }

    /** @return array<int,string> */
    public static function phoneTokens(?string $phone): array
    {
        $d = self::normalizePhone($phone);
        if (strlen($d) < 7) {
            return [];
        }
        $t = [];
        foreach ([4, 5, 6, 7] as $n) {
            $t['s:'.substr($d, -$n)] = true;
        }
        foreach ([6, 7] as $n) {
            $t['p:'.substr($d, 0, $n)] = true;
        }

        return array_map([self::class, 'hash'], array_keys($t));
    }

    /** Token to look up for a user-typed digit fragment (suffix by default; prefix when it starts with 0/3 and is 6-7 digits). @return array<int,string> */
    public static function phoneQueryTokens(string $digits): array
    {
        $d = preg_replace('/\D+/', '', $digits);
        $n = strlen($d);
        if ($n < 4 || $n > 7) {
            return [];
        }
        $t = ['s:'.$d => true];
        if ($n >= 6) {
            $t['p:'.(str_starts_with($d, '0') ? $d : '0'.$d)] = true;
        }

        return array_map([self::class, 'hash'], array_keys($t));
    }

    /** @return array<int,string> */
    public static function forCall(Call $call): array
    {
        return array_values(array_unique(array_merge(self::nameTokens($call->caller_name), self::phoneTokens($call->caller_number))));
    }

    public static function sync(Call $call): void
    {
        try {
            if (! Schema::hasTable('call_search_tokens')) {
                return;
            }
            $tokens = self::forCall($call);
            DB::table('call_search_tokens')->where('call_id', $call->id)->delete();
            if ($tokens) {
                DB::table('call_search_tokens')->insertOrIgnore(array_map(fn ($h) => ['token_bidx' => $h, 'call_id' => $call->id], $tokens));
            }
        } catch (Throwable $e) {
            report($e); // never block saving a call because of search indexing
        }
    }
}
