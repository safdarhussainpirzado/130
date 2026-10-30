<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use App\Models\User;
use Database\Seeders\HelpResponseTimeDemoSeeder;

class HelpResponseTimeReportTest extends TestCase
{
    use DatabaseTransactions;

    public function test_user_can_view_help_response_time_report_with_seeded_data(): void
    {
        $user = User::whereHas('role.permissions', function($q) {
            $q->where('name', 'reports.view');
        })->first() ?? User::first();

        // Run Demo Seeder with force flag for testing environment
        $this->artisan('db:seed', [
            '--class' => HelpResponseTimeDemoSeeder::class,
            '--force' => true,
        ]);

        $response = $this->actingAs($user)->get(route('reports.help-response-time', [
            'date_from' => now()->toDateString(),
            'date_to' => now()->toDateString(),
        ]));

        $response->assertStatus(200);
        $response->assertSee('Help Response-Time Monitoring');
        $response->assertSee('Total Helps Audited');
        $response->assertSee('Avg Tiger Response Time');
        $response->assertSee('Avg Assistance Duration');
        $response->assertSee('Avg Total Resolution Time');

        // Check for specific seeded records
        $response->assertSee('CRM-2026-88001');
        $response->assertSee('Kamran Shah');
        $response->assertSee('CRM-2026-88004');
        $response->assertSee('Dr. Asim Raza');
        $response->assertSee('CRM-2026-88009');
        $response->assertSee('Haris Sheikh');
        $response->assertSee('Tiger Reached');
    }

    public function test_user_can_export_help_response_time_report_to_excel(): void
    {
        $user = User::whereHas('role.permissions', function($q) {
            $q->where('name', 'reports.view');
        })->first() ?? User::first();

        $response = $this->actingAs($user)->get(route('reports.help-response-time', [
            'date_from' => now()->toDateString(),
            'date_to' => now()->toDateString(),
            'export' => 'excel',
        ]));

        $response->assertStatus(200);
        $this->assertTrue(
            str_contains($response->headers->get('content-type') ?? '', 'spreadsheet') ||
            str_contains($response->headers->get('content-disposition') ?? '', 'help_response_time_report')
        );
    }
}
