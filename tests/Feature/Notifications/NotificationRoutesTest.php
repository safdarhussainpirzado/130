<?php

namespace Tests\Feature\Notifications;

use App\Models\Call;
use App\Models\SupportRequest;
use App\Models\TabletAdvisory;
use App\Services\Notifications\FcmService;
use App\Services\Notifications\PushNotificationService;
use App\Services\Notifications\WebPushService;
use Illuminate\Http\Request;
use Tests\TestCase;

class NotificationRoutesTest extends TestCase
{
    private PushNotificationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PushNotificationService(
            $this->createMock(WebPushService::class),
            $this->createMock(FcmService::class)
        );
    }

    public function test_call_help_pending_url_resolves_against_router(): void
    {
        $call = new Call([
            'id' => 1234,
            'uuid' => '018f0a00-1234-7000-8000-000000001234',
            'call_number' => 'CRM-2026-01234',
        ]);
        $url = $this->service->resolveCallUrl($call);

        $this->assertNotEmpty($url);
        $this->assertStringStartsWith('/', $url);

        $request = Request::create($url, 'GET');
        $route = app('router')->getRoutes()->match($request);

        $this->assertNotNull($route);
        $this->assertEquals('calls.show', $route->getName());
    }

    public function test_advisory_created_url_resolves_to_pending_approval_ops_feed(): void
    {
        $advisory = new TabletAdvisory(['id' => 88]);
        $url = $this->service->resolveAdvisoryUrl($advisory, 'created');

        $this->assertStringContainsString('/ops/travel-advisory', $url);
        $this->assertStringContainsString('status=pending_approval', $url);

        $path = parse_url($url, PHP_URL_PATH);
        $request = Request::create($path, 'GET');
        $route = app('router')->getRoutes()->match($request);

        $this->assertNotNull($route);
        $this->assertEquals('ops.travel-advisory.index', $route->getName());
    }

    public function test_advisory_approved_url_resolves_to_ops_feed(): void
    {
        $advisory = new TabletAdvisory(['id' => 88]);
        $url = $this->service->resolveAdvisoryUrl($advisory, 'approved');

        $this->assertStringContainsString('/ops/travel-advisory', $url);

        $path = parse_url($url, PHP_URL_PATH);
        $request = Request::create($path, 'GET');
        $route = app('router')->getRoutes()->match($request);

        $this->assertNotNull($route);
        $this->assertEquals('ops.travel-advisory.index', $route->getName());
    }

    public function test_support_ticket_created_url_resolves_to_admin_management_route(): void
    {
        $support = new SupportRequest([
            'id' => 55,
            'public_id' => '018f0a00-1234-7000-8000-000000000055',
        ]);
        $url = $this->service->resolveSupportAdminUrl($support);

        $this->assertStringContainsString('/mgmt/support/requests/', $url);

        $request = Request::create($url, 'GET');
        $route = app('router')->getRoutes()->match($request);

        $this->assertNotNull($route);
        $this->assertEquals('admin.support.requests.show', $route->getName());
    }

    public function test_support_ticket_action_url_resolves_to_portal_route(): void
    {
        $support = new SupportRequest([
            'id' => 55,
            'public_id' => '018f0a00-1234-7000-8000-000000000055',
        ]);
        $url = $this->service->resolveSupportRequesterUrl($support);

        $this->assertStringContainsString('/portal/support/requests/', $url);

        $request = Request::create($url, 'GET');
        $route = app('router')->getRoutes()->match($request);

        $this->assertNotNull($route);
        $this->assertEquals('portal.support.show', $route->getName());
    }
}
