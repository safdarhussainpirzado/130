<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Call;
use App\Services\CallService;
use App\Services\ReportService;
use Carbon\Carbon;

class HelpResponseTimeMetricsTest extends TestCase
{
    /**
     * Test format_duration helper with different intervals.
     */
    public function test_format_duration_helper(): void
    {
        $this->assertEquals('45s', format_duration(45));
        $this->assertEquals('12m 30s', format_duration(750));
        $this->assertEquals('2h 15m', format_duration(8100));
        $this->assertEquals('--', format_duration(0));
        $this->assertEquals('--', format_duration(null));
    }

    /**
     * Test Call model accessors for calculated durations.
     */
    public function test_call_model_duration_accessors(): void
    {
        $call = new Call();
        
        $now = Carbon::parse('2026-09-04 10:00:00');
        $created = $now->copy();
        $dispatched = $now->copy()->addMinutes(5); // +300s
        $reached = $now->copy()->addMinutes(17);   // +720s from dispatch (12m)
        $completed = $now->copy()->addMinutes(35); // +1080s from arrival (18m), +2100s from created (35m)

        $call->created_at = $created;
        $call->inprogress_at = $dispatched;
        $call->tiger_reached_at = $reached;
        $call->completed_at = $completed;

        // Tiger Response Time = Reached (10:17) - Initiated (10:05) = 12 mins (720s)
        $this->assertEquals(720, $call->computed_tiger_response_time_sec);

        // Assistance Duration = Completed (10:35) - Reached (10:17) = 18 mins (1080s)
        $this->assertEquals(1080, $call->computed_assistance_duration_sec);

        // Total Resolution Time = Completed (10:35) - Created (10:00) = 35 mins (2100s)
        $this->assertEquals(2100, $call->computed_resolution_time_sec);
    }

    /**
     * Test display_tiger accessor from both explicit tiger_id and inprogress_remarks.
     */
    public function test_display_tiger_accessor(): void
    {
        // Case 1: Explicit tiger_id
        $call1 = new Call();
        $call1->tiger_id = 'T-102';
        $this->assertEquals('Tiger-102', $call1->display_tiger);

        // Case 2: Extracted from [Allocated Asset: T-105] in remarks
        $call2 = new Call();
        $call2->inprogress_remarks = 'Tiger dispatched [Allocated Asset: T-105] on patrol route';
        $this->assertEquals('Tiger-105', $call2->display_tiger);

        // Case 3: Extracted from [Asset: ST-1] in remarks
        $call3 = new Call();
        $call3->inprogress_remarks = 'Dispatched [Allocated Asset: ST-1] to commuter';
        $this->assertEquals('Special Tiger-1', $call3->display_tiger);

        // Case 4: None
        $call4 = new Call();
        $this->assertNull($call4->display_tiger);
    }

    /**
     * Test ReportService::formatHelpResponseTimeRow formatting logic.
     */
    public function test_format_help_response_time_row(): void
    {
        $call = new Call();
        $call->id = 999;
        $call->call_number = 'HELP-2026-9999';
        $call->created_at = Carbon::parse('2026-09-04 08:00:00');
        $call->inprogress_at = Carbon::parse('2026-09-04 08:05:00');
        $call->tiger_reached_at = Carbon::parse('2026-09-04 08:15:00');
        $call->completed_at = Carbon::parse('2026-09-04 08:40:00');
        $call->status = 'completed';
        $call->tiger_id = 'T-50';
        $call->patrol_officer_id = 'IP Rashid Minhas';
        $call->inprogress_remarks = 'Dispatched safely';
        $call->tiger_reached_remarks = 'On spot with motorist';
        $call->completed_remarks = 'Tire puncture replaced';

        $reportService = app(ReportService::class);
        $row = $reportService->formatHelpResponseTimeRow($call);

        $this->assertEquals('HELP-2026-9999', $row['call_number']);
        $this->assertEquals('Tiger-50 (IP Rashid Minhas)', $row['tiger_officer']);
        $this->assertEquals('10m 0s', $row['tiger_response_time']);      // 08:15 - 08:05 = 10m
        $this->assertEquals('25m 0s', $row['assistance_duration']);      // 08:40 - 08:15 = 25m
        $this->assertEquals('40m 0s', $row['total_resolution_time']);    // 08:40 - 08:00 = 40m
        $this->assertEquals('Resolved', $row['status']);
        $this->assertStringContainsString('On spot with motorist', $row['operator_comments']);
        $this->assertStringContainsString('Tire puncture replaced', $row['operator_comments']);
    }

    /**
     * Test valid transitions in CallService.
     */
    public function test_call_service_status_transitions(): void
    {
        $transitions = CallService::VALID_TRANSITIONS;

        // pending -> in_progress
        $this->assertContains('in_progress', $transitions['pending']);

        // in_progress -> tiger_reached AND in_progress -> completed (fallback)
        $this->assertContains('tiger_reached', $transitions['in_progress']);
        $this->assertContains('completed', $transitions['in_progress']);

        // tiger_reached -> completed
        $this->assertContains('completed', $transitions['tiger_reached']);
    }

    /**
     * Test invalid status transition throws exception.
     */
    public function test_invalid_status_transition(): void
    {
        $call = new Call();
        $call->status = 'pending';

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid state transition: pending -> completed');

        $callService = app(CallService::class);
        $user = new \App\Models\User();
        $user->id = 1;
        $callService->updateStatus($call, 'completed', $user);
    }

    /**
     * Test call_status_badge helper includes tiger_reached.
     */
    public function test_call_status_badge_helper(): void
    {
        $badge = call_status_badge('tiger_reached');
        $this->assertStringContainsString('Tiger Reached', $badge);
        $this->assertStringContainsString('bg-sky-50', $badge);
        $this->assertStringContainsString('text-sky-600', $badge);
    }
}
