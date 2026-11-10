<?php

namespace Tests\Unit;

use App\Services\ReportService;
use ReflectionClass;
use Tests\TestCase;

class CallTypeSummaryReportTest extends TestCase
{
    private ReportService $reportService;

    private ReflectionClass $reflection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->reportService = new ReportService;
        $this->reflection = new ReflectionClass(ReportService::class);
    }

    private function invokePrivateMethod(string $methodName, array $args = []): mixed
    {
        $method = $this->reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($this->reportService, $args);
    }

    /**
     * Test parseGroupByFilter with various inputs.
     */
    public function test_parse_group_by_filter(): void
    {
        // Default when empty or missing
        $this->assertEquals(['month'], $this->invokePrivateMethod('parseGroupByFilter', [[], ['month']]));
        $this->assertEquals(['date'], $this->invokePrivateMethod('parseGroupByFilter', [[], ['date']]));

        // Comma-separated string
        $this->assertEquals(
            ['month', 'date'],
            $this->invokePrivateMethod('parseGroupByFilter', [['group_by' => 'month,date'], ['month']])
        );

        // Single string
        $this->assertEquals(
            ['time'],
            $this->invokePrivateMethod('parseGroupByFilter', [['group_by' => 'time'], ['month']])
        );

        // Array
        $this->assertEquals(
            ['date', 'time'],
            $this->invokePrivateMethod('parseGroupByFilter', [['group_by' => ['date', 'time']], ['month']])
        );
    }

    /**
     * Test buildHourlyCallTypeSkeleton generates 24 intervals and merges rows correctly.
     */
    public function test_hourly_call_type_skeleton(): void
    {
        $mockRows = [
            [
                'hour_num' => 2,
                'emergency' => 5,
                'information' => 10,
                'general_help' => 3,
                'complaint' => 1,
                'junk' => 2,
                'total_voice_calls' => 19,
                'total_calls_received' => 21,
            ],
        ];

        $skeleton = $this->invokePrivateMethod('buildHourlyCallTypeSkeleton', [$mockRows]);

        $this->assertCount(24, $skeleton);
        $this->assertEquals('00:00 - 01:00', $skeleton[0]['time']);
        $this->assertEquals(0, $skeleton[0]['total_calls_received']);

        // Check hour 2 has populated data
        $this->assertEquals('02:00 - 03:00', $skeleton[2]['time']);
        $this->assertEquals(5, $skeleton[2]['emergency']);
        $this->assertEquals(10, $skeleton[2]['information']);
        $this->assertEquals(21, $skeleton[2]['total_calls_received']);
    }

    /**
     * Test buildMonthlyCallTypeSkeleton generates 12 months.
     */
    public function test_monthly_call_type_skeleton(): void
    {
        $mockRows = [
            [
                'month_num' => 1,
                'emergency' => 12,
                'information' => 30,
                'general_help' => 8,
                'complaint' => 4,
                'junk' => 10,
                'total_voice_calls' => 54,
                'total_calls_received' => 64,
            ],
        ];

        $skeleton = $this->invokePrivateMethod('buildMonthlyCallTypeSkeleton', [$mockRows]);

        $this->assertCount(12, $skeleton);
        $this->assertEquals('January', $skeleton[0]['month']);
        $this->assertEquals(12, $skeleton[0]['emergency']);
        $this->assertEquals(64, $skeleton[0]['total_calls_received']);

        $this->assertEquals('February', $skeleton[1]['month']);
        $this->assertEquals(0, $skeleton[1]['emergency']);
    }

    /**
     * Test appendCallTypeTotalsRow correctly aggregates all numeric columns.
     */
    public function test_append_call_type_totals_row(): void
    {
        $rows = [
            [
                'month' => 'January',
                'emergency' => 10,
                'information' => 20,
                'general_help' => 5,
                'complaint' => 2,
                'junk' => 3,
                'total_voice_calls' => 37,
                'ivr' => 0,
                'total_calls_received' => 40,
            ],
            [
                'month' => 'February',
                'emergency' => 15,
                'information' => 25,
                'general_help' => 10,
                'complaint' => 5,
                'junk' => 5,
                'total_voice_calls' => 55,
                'ivr' => 0,
                'total_calls_received' => 60,
            ],
        ];

        $result = $this->invokePrivateMethod('appendCallTypeTotalsRow', [$rows]);

        $this->assertCount(3, $result);
        $totalRow = end($result);
        $this->assertEquals('Total', $totalRow['month']);
        $this->assertEquals(25, $totalRow['emergency']);
        $this->assertEquals(45, $totalRow['information']);
        $this->assertEquals(15, $totalRow['general_help']);
        $this->assertEquals(7, $totalRow['complaint']);
        $this->assertEquals(8, $totalRow['junk']);
        $this->assertEquals(92, $totalRow['total_voice_calls']);
        $this->assertEquals(100, $totalRow['total_calls_received']);
    }
}
