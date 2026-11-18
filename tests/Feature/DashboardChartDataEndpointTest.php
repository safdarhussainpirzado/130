<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;

class DashboardChartDataEndpointTest extends TestCase
{
    use DatabaseTransactions;

    private function superAdmin(): User
    {
        $role = Role::firstOrCreate(
            ['name' => Role::SUPER_ADMIN],
            ['display_name' => 'Super Admin', 'scope_level' => 'national', 'is_active' => true]
        );

        return User::firstOrCreate(
            ['username' => 'test_dashboard_super_admin'],
            [
                'uuid' => (string) Str::uuid(),
                'full_name' => 'Dashboard Test Super Admin',
                'email' => 'dash_test_' . Str::random(5) . '@nhmp.gov.pk',
                'role_id' => $role->id,
                'is_active' => true,
                'password' => bcrypt('Password123!'),
            ]
        );
    }

    /**
     * Test SLA endpoint returns four_slas structure for authenticated users.
     */
    public function test_sla_chart_data_endpoint_returns_aerodynamic_slas(): void
    {
        $user = $this->superAdmin();

        $response = $this->actingAs($user)->getJson('/dashboard/chart-data?chart=sla&range=today');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'range',
            'range_label',
            'four_slas' => [
                'p1' => ['eligible', 'met', 'percent', 'avg_sec', 'target_sec'],
                'p2' => ['eligible', 'met', 'percent', 'avg_sec', 'target_sec'],
                'p3' => ['eligible', 'met', 'percent', 'avg_sec', 'target_sec'],
                'women' => ['eligible', 'met', 'percent', 'avg_sec', 'target_sec'],
            ],
            'p1',
            'p2',
            'p3',
            'women',
        ]);
    }

    /**
     * Test Sector and Zone endpoints return 200.
     */
    public function test_sector_and_zone_chart_endpoints(): void
    {
        $user = $this->superAdmin();

        $resSector = $this->actingAs($user)->getJson('/dashboard/chart-data?chart=sector&range=today');
        $resSector->assertStatus(200);

        $resZone = $this->actingAs($user)->getJson('/dashboard/chart-data?chart=zone&range=today');
        $resZone->assertStatus(200);
    }

    /**
     * Test unauthenticated access is unauthorized / redirected.
     */
    public function test_unauthenticated_request_is_blocked(): void
    {
        $response = $this->getJson('/dashboard/chart-data?chart=sla&range=today');
        $response->assertUnauthorized();
    }
}
