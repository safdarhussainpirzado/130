<?php

namespace App\Support;

/** Session hash (never the raw id), request id and node name for audit/login rows. Never throws. */
class ForensicContext
{
    public static function all(): array
    {
        try {
            $sid = app()->bound('session.store') && app('session.store')->isStarted() ? app('session.store')->getId() : null;
            return [
                'session_hash' => $sid ? substr(hash('sha256', $sid), 0, 16) : null,
                'request_id'   => app()->bound('request') ? app('request')->attributes->get('request_id') : null,
                'node'         => substr((string) gethostname(), 0, 32),
            ];
        } catch (\Throwable) {
            return ['session_hash' => null, 'request_id' => null, 'node' => null];
        }
    }
}
