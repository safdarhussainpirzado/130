<?php

if (!function_exists('setting')) {
    function setting(string $key, mixed $default = null): mixed
    {
        return \App\Models\SystemSetting::get($key, $default);
    }
}

if (!function_exists('format_duration')) {
    function format_duration(?int $seconds): string
    {
        if (!$seconds) return '--';
        $h = floor($seconds / 3600);
        $m = floor(($seconds % 3600) / 60);
        $s = $seconds % 60;
        if ($h > 0) return "{$h}h {$m}m";
        if ($m > 0) return "{$m}m {$s}s";
        return "{$s}s";
    }
}

if (!function_exists('call_status_badge')) {
    function call_status_badge(string $status): string
    {
        $status = strtolower($status);
        $badges = [
            'pending' => '<span class="px-3 py-1 text-[9px] font-black uppercase tracking-[0.1em] rounded-full bg-slate-100 text-slate-500 border border-slate-200/50 shadow-sm shadow-slate-200/20">Pending</span>',
            'in_progress' => '<span class="px-3 py-1 text-[9px] font-black uppercase tracking-[0.1em] rounded-full bg-amber-50 text-amber-600 border border-amber-200/50 shadow-sm shadow-amber-500/10">In Progress</span>',
            'tiger_reached' => '<span class="px-3 py-1 text-[9px] font-black uppercase tracking-[0.1em] rounded-full bg-sky-50 text-sky-600 border border-sky-200/50 shadow-sm shadow-sky-500/10">Tiger Reached</span>',
            'completed' => '<span class="px-3 py-1 text-[9px] font-black uppercase tracking-[0.1em] rounded-full bg-emerald-50 text-emerald-600 border border-emerald-200/50 shadow-sm shadow-emerald-500/10">Completed</span>',
            'cancelled' => '<span class="px-3 py-1 text-[9px] font-black uppercase tracking-[0.1em] rounded-full bg-rose-50 text-rose-600 border border-rose-200/50 shadow-sm shadow-rose-500/10">Cancelled</span>',
            'junk' => '<span class="px-3 py-1 text-[9px] font-black uppercase tracking-[0.1em] rounded-full bg-slate-200 text-slate-400 border border-slate-300 shadow-sm italic">Junk</span>',
            'forwarded' => '<span class="px-3 py-1 text-[9px] font-black uppercase tracking-[0.1em] rounded-full bg-blue-50 text-blue-600 border border-blue-200/50 shadow-sm shadow-blue-500/10">Forwarded</span>',
        ];
        return $badges[$status] ?? "<span class='px-3 py-1 text-[9px] font-black uppercase rounded-full bg-slate-100'>{$status}</span>";
    }
}

if (!function_exists('priority_badge')) {
    function priority_badge(int $priority): string
    {
        $badges = [
            1 => '<span class="px-2.5 py-1 text-[8px] font-black uppercase tracking-widest rounded-lg bg-rose-600/10 text-rose-600 border border-rose-600/20 shadow-sm shadow-rose-500/10 flex items-center gap-1.5 w-fit"><span class="w-1.5 h-1.5 rounded-full bg-rose-600 animate-pulse"></span> P1 — Critical</span>',
            2 => '<span class="px-2.5 py-1 text-[8px] font-black uppercase tracking-widest rounded-lg bg-amber-500/10 text-amber-600 border border-amber-500/20 shadow-sm shadow-amber-500/10 flex items-center gap-1.5 w-fit"><span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span> P2 — High</span>',
            3 => '<span class="px-2.5 py-1 text-[8px] font-black uppercase tracking-widest rounded-lg bg-blue-500/10 text-blue-600 border border-blue-500/20 shadow-sm shadow-blue-500/10 flex items-center gap-1.5 w-fit"><span class="w-1.5 h-1.5 rounded-full bg-blue-500"></span> P3 — Medium</span>',
            4 => '<span class="px-2.5 py-1 text-[8px] font-black uppercase tracking-widest rounded-lg bg-emerald-500/10 text-emerald-600 border border-emerald-500/20 shadow-sm shadow-emerald-500/10 flex items-center gap-1.5 w-fit"><span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> P4 — Low</span>',
            5 => '<span class="px-2.5 py-1 text-[8px] font-black uppercase tracking-widest rounded-lg bg-slate-400/10 text-slate-500 border border-slate-400/20 shadow-sm flex items-center gap-1.5 w-fit"><span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span> P5 — Junk</span>',
        ];
        return $badges[$priority] ?? "<span class='px-2.5 py-1 text-[8px] font-black uppercase rounded-lg bg-slate-100'>P{$priority}</span>";
    }
}

if (!function_exists('docker_secret')) {
    /**
     * Resolves a secret from Docker Secrets (/run/secrets/{name}) with local file and env() fallback.
     */
    function docker_secret(string $name, mixed $default = null): mixed
    {
        $path = "/run/secrets/{$name}";
        if (is_file($path) && is_readable($path)) {
            $val = trim((string) file_get_contents($path));
            if ($val !== '') {
                return $val;
            }
        }

        // Host / local development fallback: check docker/secrets/{name}.txt
        $localSecretPath = function_exists('base_path') ? base_path("docker/secrets/{$name}.txt") : (__DIR__ . "/../../docker/secrets/{$name}.txt");
        if (is_file($localSecretPath) && is_readable($localSecretPath)) {
            $val = trim((string) file_get_contents($localSecretPath));
            if ($val !== '') {
                return $val;
            }
        }

        return env(strtoupper($name), $default);
    }
}

if (!function_exists('phone_blind_index')) {
    /**
     * Computes a deterministic HMAC-SHA256 blind index for exact phone lookups.
     * Preserves sub-10ms CTI search speed without exposing raw caller telephone numbers.
     */
    function phone_blind_index(?string $phone): ?string
    {
        if (empty($phone)) {
            return null;
        }
        $digits = preg_replace('/[^0-9]/', '', (string)$phone);
        if ($digits === '') {
            return null;
        }
        // Canonicalize Pakistan dialing formats (03XX, +923XX, 00923XX)
        if (str_starts_with($digits, '0092')) {
            $digits = '0' . substr($digits, 4);
        } elseif (str_starts_with($digits, '92') && strlen($digits) === 12) {
            $digits = '0' . substr($digits, 2);
        } elseif (strlen($digits) === 10 && !str_starts_with($digits, '0')) {
            $digits = '0' . $digits;
        }
        $salt = (function_exists('app') && app()->has('config')) ? config('app.bidx_salt') : null;
        $salt = $salt ?: docker_secret('bidx_salt', 'nhmp_130_sec_bidx_default_salt_2026');
        return hash_hmac('sha256', $digits, $salt);
    }
}
