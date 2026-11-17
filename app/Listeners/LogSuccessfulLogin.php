<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Login;
use App\Models\LoginLog;
use Illuminate\Support\Facades\Request;

class LogSuccessfulLogin
{
    public function handle(Login $event): void
    {
        // Password/2FA controllers already write an enriched LoginLog row for this request.
        if (Request::instance()->attributes->has('geo_eval')) {
            return;
        }

        LoginLog::create([
            'user_id' => $event->user->getAuthIdentifier(),
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'login_at' => now(),
        ]);
    }
}
