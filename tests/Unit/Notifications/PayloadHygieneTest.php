<?php

namespace Tests\Unit\Notifications;

use App\Models\Call;
use App\Models\CallType;
use App\Models\Office;
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

    public function test_help_pending_payload_contains_zero_pii(): void
    {
        // Mock a call with sensitive citizen details
        $call = new Call([
            'id' => 9999,
            'call_number' => 'CRM-2026-09999',
            'status' => 'pending',
            'priority' => 'critical',
            'caller_name' => 'Safdar Hussain Citizen',
            'caller_phone' => '03001234567',
            'caller_cnic' => '42101-1234567-1',
            'remarks' => 'Sensitive victim medical state: traumatic injury',
        ]);

        $call->setRelation('office', new Office(['name' => 'Beat 10 (M-2)']));
        $call->setRelation('callType', new CallType(['name' => 'Road Accident']));

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('resolveDeskRecipientsForHelp');
        $method->setAccessible(true);

        // Verify that the title and body formulated do not contain PII
        $priorityLabel = strtoupper((string) ($call->priority ?? 'NORMAL'));
        $title = $this->service->sanitizeText("🚨 NHMP 130 [{$priorityLabel}]: Pending Help {$call->call_number}", 80);
        $body = $this->service->sanitizeText('Beat: Beat 10 (M-2) | Type: Road Accident | Requires immediate operational dispatch.', 200);

        $this->assertStringNotContainsString('Safdar', $title);
        $this->assertStringNotContainsString('Safdar', $body);
        $this->assertStringNotContainsString('03001234567', $title);
        $this->assertStringNotContainsString('03001234567', $body);
        $this->assertStringNotContainsString('42101', $title);
        $this->assertStringNotContainsString('42101', $body);
        $this->assertStringNotContainsString('traumatic', $title);
        $this->assertStringNotContainsString('traumatic', $body);

        $this->assertStringContainsString('CRM-2026-09999', $title);
        $this->assertStringContainsString('Beat 10 (M-2)', $body);
    }
}
