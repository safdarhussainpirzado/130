<?php

namespace Tests\Feature\Security;

use App\Models\User;
use App\Octane\RequestStateReset;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/** No database needed: exercises the worker reset gates and trusted-proxy client-IP resolution. */
class WorkerIsolationAndProxyTest extends TestCase
{
    private function reset(): bool
    {
        return RequestStateReset::run($this->app, $this->baseConfig ?? config()->all(), 'en');
    }

    private ?array $baseConfig = null;

    public function test_sequential_requests_never_see_previous_identity(): void
    {
        $this->baseConfig = config()->all();
        foreach ([['id' => 101, 'username' => 'Awais_M'], ['id' => 202, 'username' => 'ali_hassan130']] as $u) {
            $user = new User(); $user->id = $u['id']; $user->username = $u['username'];
            Auth::guard('web')->setUser($user);
            $this->assertSame($u['id'], Auth::guard('web')->id());
            $this->assertTrue($this->reset(), 'reset gates must all pass');
            $this->assertFalse(Auth::guard('web')->hasUser(), 'guard must be empty for the next request');
            $this->assertNull(Auth::guard('web')->user());
        }
        // third request: guest
        $this->assertFalse(Auth::guard('web')->check());
    }

    public function test_queued_cookies_runtime_config_and_locale_are_reset(): void
    {
        $this->baseConfig = config()->all();
        cookie()->queue('leak', 'userA', 5);
        config(['app.name' => 'POLLUTED']);
        app()->setLocale('xx');
        $this->assertTrue($this->reset());
        $this->assertSame([], cookie()->getQueuedCookies());
        $this->assertNotSame('POLLUTED', config('app.name'));
        $this->assertSame('en', app()->getLocale());
    }

    public function test_failing_gate_is_reported_not_swallowed(): void
    {
        $this->baseConfig = config()->all();
        $this->app->bind('cookie', fn () => throw new \RuntimeException('boom'));
        $this->app->forgetInstance('cookie');
        $this->assertFalse($this->reset(), 'a failed gate must poison the worker (returns false)');
    }

    public function test_forged_forwarded_for_is_ignored_behind_trusted_proxies(): void
    {
        Route::get('/_ip_probe', fn () => request()->ip());
        // LB 10.1.2.62 appended the real client; attacker forged the leftmost entry; nginx/haproxy hops are docker IPs.
        $r = $this->withServerVariables(['REMOTE_ADDR' => '172.18.0.9'])
            ->withHeaders(['X-Forwarded-For' => '6.6.6.6, 203.0.113.9, 10.1.2.62, 172.18.0.10'])->get('/_ip_probe');
        $this->assertSame('203.0.113.9', $r->getContent());
    }

    public function test_untrusted_direct_caller_cannot_spoof_ip(): void
    {
        Route::get('/_ip_probe2', fn () => request()->ip());
        $r = $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.7'])
            ->withHeaders(['X-Forwarded-For' => '6.6.6.6'])->get('/_ip_probe2');
        $this->assertSame('198.51.100.7', $r->getContent());
    }
}
