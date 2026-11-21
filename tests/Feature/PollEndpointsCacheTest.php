<?php

namespace Tests\Feature;

use App\Models\Office;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PollEndpointsCacheTest extends TestCase
{
    use DatabaseTransactions;

    protected User $supervisorUser;
    protected User $sectorAdminUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class]);

        $zone = Office::firstOrCreate(
            ['name' => 'Poll Test Zone'],
            ['type' => 'zone', 'is_active' => true]
        );

        $sector = Office::firstOrCreate(
            ['name' => 'Poll Test Sector'],
            ['type' => 'sector', 'parent_id' => $zone->id, 'is_active' => true]
        );

        $supervisorRole = Role::firstOrCreate(['name' => 'agent_supervisor'], ['scope_level' => 'national']);
        $sectorRole = Role::firstOrCreate(['name' => 'sector_admin'], ['scope_level' => 'sector']);

        $this->supervisorUser = User::firstOrCreate(
            ['email' => 'supervisor_poll@nhmp.gov.pk'],
            [
                'name' => 'Supervisor Poll',
                'username' => 'sup_poll',
                'password' => bcrypt('password'),
                'role_id' => $supervisorRole->id,
                'office_id' => $zone->id,
                'is_active' => true,
            ]
        );

        $this->sectorAdminUser = User::firstOrCreate(
            ['email' => 'sector_poll@nhmp.gov.pk'],
            [
                'name' => 'Sector Admin Poll',
                'username' => 'sec_poll',
                'password' => bcrypt('password'),
                'role_id' => $sectorRole->id,
                'office_id' => $sector->id,
                'is_active' => true,
            ]
        );
    }

    public function test_emergency_check_returns_no_cache_headers(): void
    {
        $response = $this->actingAs($this->supervisorUser)
            ->getJson('/api/v1/realtime/emergency-check?last_id=0');

        $response->assertStatus(200);
        $this->assertStringContainsString('no-cache', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function test_notifications_poll_returns_no_cache_headers(): void
    {
        $response = $this->actingAs($this->supervisorUser)
            ->getJson('/api/notifications/poll');

        $response->assertStatus(200);
        $this->assertStringContainsString('no-cache', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function test_travel_advisory_events_returns_no_cache_headers(): void
    {
        $response = $this->actingAs($this->supervisorUser)
            ->getJson(route('ops.travel-advisory.events'));

        $response->assertStatus(200);
        $this->assertStringContainsString('no-cache', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function test_support_pending_count_returns_no_cache_headers(): void
    {
        $response = $this->actingAs($this->supervisorUser)
            ->getJson(route('admin.support.pendingCount'));

        $response->assertStatus(200);
        $this->assertStringContainsString('no-cache', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }
}
