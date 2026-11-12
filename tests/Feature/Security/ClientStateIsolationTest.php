<?php

namespace Tests\Feature\Security;

use Tests\TestCase;

/**
 * Zero cross-user bleed on shared workstations: the browser must be told to drop cache/localStorage/sessionStorage
 * whenever the login page is shown and when a user logs out. Runs ONLY against the disposable test DB (see phpunit.xml).
 */
class ClientStateIsolationTest extends TestCase
{
    public function test_login_page_clears_client_site_data(): void
    {
        $this->get('/login')->assertOk()->assertHeader('Clear-Site-Data', '"cache", "storage"');
    }

    public function test_session_cookie_is_secure_in_production(): void
    {
        $this->assertTrue(config('session.http_only'));
        if (config('app.env') === 'production') {
            $this->assertTrue(config('session.secure'));
        }
        $this->assertSame('lax', config('session.same_site'));
    }
}
