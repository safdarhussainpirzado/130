<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class GeospatialLookupTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Gate::define('calls.api_lookup', fn () => true);
    }

    public function test_geospatial_lookup_handles_missing_parameters(): void
    {
        $user = User::first() ?? User::factory()->create();

        $response = $this->actingAs($user)->getJson('/ajax/geospatial-lookup');

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);
        $this->assertIsArray($response->json('landmarks'));
    }

    public function test_geospatial_lookup_handles_empty_string_parameters(): void
    {
        $user = User::first() ?? User::factory()->create();

        $response = $this->actingAs($user)->getJson('/ajax/geospatial-lookup?road=&km_marker=&direction=');

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);
        $this->assertIsArray($response->json('landmarks'));
    }

    public function test_geospatial_lookup_handles_numeric_inputs(): void
    {
        $user = User::first() ?? User::factory()->create();

        $response = $this->actingAs($user)->getJson('/ajax/geospatial-lookup?road=2&km_marker=504&direction=1');

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);
        $this->assertIsArray($response->json('landmarks'));
    }

    public function test_geospatial_lookup_handles_odd_and_special_character_inputs(): void
    {
        $user = User::first() ?? User::factory()->create();

        $response = $this->actingAs($user)->getJson('/ajax/geospatial-lookup?road=M-2%20%28NB%29&km_marker=327.5-SB&direction=Northbound%21%40%23');

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);
        $this->assertIsArray($response->json('landmarks'));
    }
}
