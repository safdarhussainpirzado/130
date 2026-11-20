<?php

namespace Tests\Unit\Notifications;

use App\Models\Call;
use App\Models\User;
use App\Services\Notifications\FcmService;
use App\Services\Notifications\PushNotificationService;
use App\Services\Notifications\WebPushService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class DeduplicationAndRateLimitingTest extends TestCase
{
    private PushNotificationService $service;

    private $webPushMock;

    private $fcmMock;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        RateLimiter::clear('push:rate:101');

        $this->webPushMock = $this->createMock(WebPushService::class);
        $this->fcmMock = $this->createMock(FcmService::class);

        $this->service = new class($this->webPushMock, $this->fcmMock) extends PushNotificationService
        {
            public function dispatchForTesting(array $userIds, array $payload, string $channel, string|int $eventId): array
            {
                return $this->dispatchToSubscriptions($userIds, $payload, $channel, $eventId);
            }
        };
    }

    public function test_deduplication_suppresses_duplicate_notifications_within_ttl(): void
    {
        $userId = 101;
        $eventId = 5001;
        $channel = 'help_pending';
        $payload = ['title' => 'Test', 'body' => 'Body', 'data' => ['url' => '/calls/5001']];

        // First call should register dedup key in Cache
        $result1 = $this->service->dispatchForTesting([$userId], $payload, $channel, $eventId);
        $this->assertEquals(1, $result1['recipients']);

        // Second call with same (channel, eventId, userId) should be suppressed by deduplication
        $result2 = $this->service->dispatchForTesting([$userId], $payload, $channel, $eventId);
        $this->assertEquals(0, $result2['recipients'], 'Second duplicate push must be suppressed');
    }

    public function test_rate_limiter_caps_notifications_at_30_per_minute(): void
    {
        $userId = 101;
        $channel = 'help_pending';
        $payload = ['title' => 'Test', 'body' => 'Body', 'data' => ['url' => '/calls/test']];

        // Dispatch 30 unique event IDs
        for ($i = 1; $i <= 30; $i++) {
            $result = $this->service->dispatchForTesting([$userId], $payload, $channel, "event-{$i}");
            $this->assertEquals(1, $result['recipients'], "Notification {$i} should be allowed within rate limit");
        }

        // 31st notification for the same user must be suppressed by the rate limiter
        $result31 = $this->service->dispatchForTesting([$userId], $payload, $channel, 'event-31');
        $this->assertEquals(0, $result31['recipients'], '31st push within 1 minute must be suppressed by rate limit');
    }
}
