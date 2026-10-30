<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Call;
use Illuminate\Support\Str;

class CallSecurityTest extends TestCase
{
    /**
     * Test that sequential integer ID access redirects with 301 to UUIDv7 canonical URL (Anti-IDOR / Anti-Enumeration).
     */
    public function test_legacy_integer_id_permanently_redirects_to_uuid_canonical_url(): void
    {
        // Find an active user with call permissions
        $user = User::where('is_active', true)->first();
        if (!$user) {
            $user = User::first();
        }

        // Find or create a call with UUID
        $call = Call::whereNotNull('uuid')->first();
        if (!$call) {
            $this->markTestSkipped('No call with UUID available for security test.');
        }

        // Request with legacy integer ID: /calls/{id}
        $response = $this->actingAs($user)->get("/calls/{$call->id}");

        // Must 301 redirect to canonical /calls/{uuid}
        $response->assertStatus(301);
        $response->assertRedirect("/calls/{$call->uuid}");
    }

    /**
     * Test that accessing canonical /calls/{uuid} succeeds with 200 OK for authenticated user.
     */
    public function test_canonical_uuid_access_returns_success_for_authorized_user(): void
    {
        $user = User::where('is_active', true)->first() ?: User::first();
        $call = Call::whereNotNull('uuid')->first();

        if (!$call) {
            $this->markTestSkipped('No call with UUID available for security test.');
        }

        $response = $this->actingAs($user)->get("/calls/{$call->uuid}");
        $response->assertStatus(200);
        $response->assertSee($call->call_number ?? $call->caller_number);
    }

    /**
     * Test that unauthenticated guests cannot access call details.
     */
    public function test_unauthenticated_guests_are_redirected_to_login(): void
    {
        $call = Call::whereNotNull('uuid')->first();
        if (!$call) {
            $this->markTestSkipped('No call with UUID available for security test.');
        }

        $response = $this->get("/calls/{$call->uuid}");
        $response->assertRedirect('/login');
    }
}
