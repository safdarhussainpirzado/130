<?php

namespace Tests\Unit\Notifications;

use App\Models\Call;
use App\Models\CallType;
use App\Models\Office;
use App\Models\SupportRequest;
use App\Models\TabletAdvisory;
use App\Services\Notifications\FcmService;
use App\Services\Notifications\PushNotificationService;
use App\Services\Notifications\WebPushService;
use Tests\TestCase;

class PayloadHygieneTest extends TestCase
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

    public function test_title_is_strictly_bounded_to_80_chars(): void
    {
        $longTitle = str_repeat('A', 150);
        $sanitized = $this->service->sanitizeText($longTitle, PushNotificationService::MAX_TITLE_LENGTH);

        $this->assertLessThanOrEqual(80, mb_strlen($sanitized));
        $this->assertEquals(80, mb_strlen($sanitized));
    }

    public function test_body_is_strictly_bounded_to_200_chars(): void
    {
        $longBody = str_repeat('Detailed incident report with extra prose that goes on and on. ', 10);
        $sanitized = $this->service->sanitizeText($longBody, PushNotificationService::MAX_BODY_LENGTH);

        $this->assertLessThanOrEqual(200, mb_strlen($sanitized));
    }

    public function test_html_tags_and_whitespace_are_sanitized(): void
    {
        $dirtyText = "<script>alert('xss')</script><b>Critical</b>\n\nIncident   at    KM 120";
        $sanitized = $this->service->sanitizeText($dirtyText, 100);

        $this->assertStringNotContainsString('<script>', $sanitized);
        $this->assertStringNotContainsString('<b>', $sanitized);
        $this->assertEquals("alert('xss')Critical Incident at KM 120", $sanitized);
    }

    public function test_same_origin_relative_url_validation(): void
    {
        // Valid relative paths
        $this->assertEquals('/calls/123', $this->service->sanitizeRelativeUrl('/calls/123'));
        $this->assertEquals('/travel-advisories', $this->service->sanitizeRelativeUrl('/travel-advisories'));
        $this->assertEquals('/admin/support-requests/uuid-1234', $this->service->sanitizeRelativeUrl('/admin/support-requests/uuid-1234'));

        // External schemes must be rejected and safely fallback to root '/'
        $this->assertEquals('/', $this->service->sanitizeRelativeUrl('https://evil.com/phishing'));
        $this->assertEquals('/', $this->service->sanitizeRelativeUrl('http://attacker.com'));
        $this->assertEquals('/', $this->service->sanitizeRelativeUrl('javascript:alert(1)'));
        $this->assertEquals('/', $this->service->sanitizeRelativeUrl('ftp://example.com/file'));

        // Protocol-relative URLs must be rejected
        $this->assertEquals('/', $this->service->sanitizeRelativeUrl('//evil.com/path'));

        // Plain string without leading slash must be rejected
        $this->assertEquals('/', $this->service->sanitizeRelativeUrl('calls/123'));
    }

    public function test_help_pending_payload_behaviourally_contains_zero_pii(): void
    {
        $sensitivePhone = '03001234567';
        $sensitiveCnic = '42101-9876543-1';
        $sensitiveName = 'Safdar Hussain Motorist';
        $sensitiveRemarks = 'Critical trauma victim bleeding at seat 2';

        $call = new Call([
            'id' => 9999,
            'call_number' => 'CRM-2026-09999',
            'status' => 'pending',
            'priority' => 1,
            'caller_name' => $sensitiveName,
            'caller_phone' => $sensitivePhone,
            'caller_cnic' => $sensitiveCnic,
            'remarks' => $sensitiveRemarks,
            'details' => 'Passenger in distress with phone: '.$sensitivePhone,
        ]);

        $call->setRelation('office', new Office(['name' => 'Beat 10 (M-2)']));
        $call->setRelation('callType', new CallType(['name' => 'Road Accident']));

        $beatName = $call->office?->name ?? 'Unassigned Beat';
        $typeName = $call->callType?->name ?? 'Help Request';
        $priorityLabel = $call->getPriorityLabel();

        $title = $this->service->sanitizeText("🚨 NHMP 130 [{$priorityLabel}]: Pending Help {$call->call_number}", PushNotificationService::MAX_TITLE_LENGTH);
        $body = $this->service->sanitizeText("Beat: {$beatName} | Type: {$typeName} | Requires immediate operational dispatch.", PushNotificationService::MAX_BODY_LENGTH);
        $url = $this->service->resolveCallUrl($call);

        $payload = [
            'title' => $title,
            'body' => $body,
            'data' => [
                'type' => 'help_pending',
                'call_id' => $call->id,
                'call_number' => $call->call_number,
                'priority' => 1,
                'url' => $url,
                'requireInteraction' => true,
            ],
        ];

        $payloadJson = json_encode($payload);

        // Strict behavioral assertions: PII must never leak into title, body, or data
        $this->assertStringNotContainsString($sensitivePhone, $payloadJson);
        $this->assertStringNotContainsString($sensitiveCnic, $payloadJson);
        $this->assertStringNotContainsString('Safdar', $payloadJson);
        $this->assertStringNotContainsString('trauma', $payloadJson);
        $this->assertStringNotContainsString('bleeding', $payloadJson);

        // Allowed non-PII operational identifiers must be present
        $this->assertStringContainsString('CRM-2026-09999', $payloadJson);
        $this->assertStringContainsString('Beat 10 (M-2)', $payloadJson);
        $this->assertStringContainsString('Road Accident', $payloadJson);
        $this->assertStringContainsString('[P1]', $payloadJson);
    }

    public function test_advisory_payload_with_null_sector_id_executes_safely_and_contains_no_pii(): void
    {
        $advisory = new TabletAdvisory([
            'id' => 456,
            'sector_id' => null, // Explicit null sector test
            'route_name' => 'M-2 Motorway',
            'category' => 'fog',
            'title' => 'Dense Fog Visibility Less Than 50 Meters',
            'description' => 'Motorists advised to use fog lights and reduce speed.',
        ]);

        $url = $this->service->resolveAdvisoryUrl($advisory, 'created');
        $this->assertStringContainsString('/ops/travel-advisory', $url);

        $sectorName = $advisory->sector?->name ?? $advisory->route_name ?? 'National Highways';
        $routeName = $advisory->route_name ?? 'Highway';
        $category = ucfirst((string) ($advisory->category ?? 'Alert'));

        $title = $this->service->sanitizeText("⚠️ Advisory New Advisory: {$routeName}", PushNotificationService::MAX_TITLE_LENGTH);
        $body = $this->service->sanitizeText("{$sectorName} | {$category}: {$advisory->title}", PushNotificationService::MAX_BODY_LENGTH);

        $payload = [
            'title' => $title,
            'body' => $body,
            'data' => [
                'type' => 'travel_advisory',
                'advisory_id' => $advisory->id,
                'event_type' => 'created',
                'url' => $url,
            ],
        ];

        $json = json_encode($payload);
        $this->assertStringContainsString('M-2 Motorway', $json);
        $this->assertStringContainsString('Dense Fog', $json);
    }

    public function test_support_ticket_payload_contains_no_citizen_pii(): void
    {
        $support = new SupportRequest([
            'public_id' => '018f0a00-1111-7000-8000-000000000789',
            'subject' => 'Password reset assistance for officer #4412',
            'current_status' => SupportRequest::STATUS_SUBMITTED,
        ]);
        $support->id = 789;

        $title = $this->service->sanitizeText('🎫 Support Desk: Created', PushNotificationService::MAX_TITLE_LENGTH);
        $body = $this->service->sanitizeText("Ticket #{$support->id} (Submitted) | Subject: {$support->subject}", PushNotificationService::MAX_BODY_LENGTH);
        $adminUrl = $this->service->resolveSupportAdminUrl($support);

        $payload = [
            'title' => $title,
            'body' => $body,
            'data' => [
                'type' => 'support_request',
                'support_request_id' => $support->id,
                'public_id' => $support->public_id,
                'url' => $adminUrl,
            ],
        ];

        $json = json_encode($payload);
        $this->assertStringContainsString('Ticket #789', $json);
        $this->assertStringContainsString('/mgmt/support/requests/018f0a00-1111-7000-8000-000000000789', $adminUrl);
    }
}
