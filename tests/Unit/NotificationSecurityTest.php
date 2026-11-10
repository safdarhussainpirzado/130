<?php

namespace Tests\Unit;

use App\Models\User;
use Tests\TestCase;

class NotificationSecurityTest extends TestCase
{
    /**
     * Test that getNotificationsPoll does not leak internal stack traces or server paths.
     */
    public function test_notifications_poll_does_not_leak_stack_trace(): void
    {
        try {
            $user = User::whereHas('role.permissions', fn($q) => $q->where('name', 'calls.api_lookup'))->first() ?? User::first();
        } catch (\Throwable $e) {
            $this->markTestSkipped('Database not available for notification security check: ' . $e->getMessage());
        }

        if (! $user) {
            $this->markTestSkipped('No user found');
        }

        \Illuminate\Support\Facades\Gate::define('calls.api_lookup', fn () => true);

        $response = $this->actingAs($user)->getJson('/api/notifications/poll?since=invalid-date-malformed-input');

        $data = $response->json();
        $this->assertArrayNotHasKey('trace', $data, 'Security vulnerability: Stack trace leaked to client!');
        $this->assertStringNotContainsString('Stack trace:', json_encode($data));
        $this->assertStringNotContainsString('/var/www/html', json_encode($data));
        $this->assertArrayHasKey('new_calls', $data);
        $this->assertArrayHasKey('timestamp', $data);
    }
}
