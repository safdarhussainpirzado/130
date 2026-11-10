<?php

namespace Tests\Unit;

use App\Models\Call;
use App\Models\CallStatusHistory;
use App\Models\CallSubType;
use App\Models\CallType;
use App\Models\Carriageway;
use App\Models\Office;
use App\Models\OutgoingCall;
use App\Models\User;
use App\Models\VehicleType;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Tests\TestCase;

class CallModelRelationsTest extends TestCase
{
    private Call $call;

    protected function setUp(): void
    {
        parent::setUp();
        $this->call = new Call();
    }

    public function test_agent_relationship(): void
    {
        $relation = $this->call->agent();
        $this->assertInstanceOf(BelongsTo::class, $relation);
        $this->assertInstanceOf(User::class, $relation->getRelated());
        $this->assertEquals('agent_id', $relation->getForeignKeyName());
    }

    public function test_tiger_reached_by_relationship(): void
    {
        $relation = $this->call->tigerReachedBy();
        $this->assertInstanceOf(BelongsTo::class, $relation);
        $this->assertInstanceOf(User::class, $relation->getRelated());
        $this->assertEquals('tiger_reached_status_by', $relation->getForeignKeyName());
    }

    public function test_inprogress_status_by_relationship(): void
    {
        $relation = $this->call->inprogressStatusBy();
        $this->assertInstanceOf(BelongsTo::class, $relation);
        $this->assertInstanceOf(User::class, $relation->getRelated());
        $this->assertEquals('inprogress_status_by', $relation->getForeignKeyName());
    }

    public function test_completed_status_by_relationship(): void
    {
        $relation = $this->call->completedStatusBy();
        $this->assertInstanceOf(BelongsTo::class, $relation);
        $this->assertInstanceOf(User::class, $relation->getRelated());
        $this->assertEquals('completed_status_by', $relation->getForeignKeyName());
    }

    public function test_call_type_relationship(): void
    {
        $relation = $this->call->callType();
        $this->assertInstanceOf(BelongsTo::class, $relation);
        $this->assertInstanceOf(CallType::class, $relation->getRelated());
    }

    public function test_call_sub_type_relationship(): void
    {
        $relation = $this->call->callSubType();
        $this->assertInstanceOf(BelongsTo::class, $relation);
        $this->assertInstanceOf(CallSubType::class, $relation->getRelated());
    }

    public function test_office_relationship(): void
    {
        $relation = $this->call->office();
        $this->assertInstanceOf(BelongsTo::class, $relation);
        $this->assertInstanceOf(Office::class, $relation->getRelated());
    }

    public function test_carriageway_relationship(): void
    {
        $relation = $this->call->carriageway();
        $this->assertInstanceOf(BelongsTo::class, $relation);
        $this->assertInstanceOf(Carriageway::class, $relation->getRelated());
    }

    public function test_vehicle_type_relationship(): void
    {
        $relation = $this->call->vehicleType();
        $this->assertInstanceOf(BelongsTo::class, $relation);
        $this->assertInstanceOf(VehicleType::class, $relation->getRelated());
    }

    public function test_status_history_relationship(): void
    {
        $relation = $this->call->statusHistory();
        $this->assertInstanceOf(HasMany::class, $relation);
        $this->assertInstanceOf(CallStatusHistory::class, $relation->getRelated());
    }

    public function test_outgoing_calls_relationship(): void
    {
        $relation = $this->call->outgoingCalls();
        $this->assertInstanceOf(HasMany::class, $relation);
        $this->assertInstanceOf(OutgoingCall::class, $relation->getRelated());
    }

    public function test_display_tiger_formatting(): void
    {
        $callT = new Call(['tiger_id' => 'T14']);
        $this->assertEquals('Tiger-14', $callT->display_tiger);

        $callST = new Call(['tiger_id' => 'ST-03']);
        $this->assertEquals('Special Tiger-03', $callST->display_tiger);

        $callFromRemarks = new Call([
            'tiger_id' => null,
            'inprogress_remarks' => 'Dispatched unit [Allocated Asset: ST-05] to location',
        ]);
        $this->assertEquals('Special Tiger-05', $callFromRemarks->display_tiger);

        $callNull = new Call(['tiger_id' => null, 'inprogress_remarks' => null]);
        $this->assertNull($callNull->display_tiger);
    }

    public function test_computed_time_metrics(): void
    {
        $now = Carbon::now();
        $call = new Call([
            'inprogress_at' => $now->copy()->subMinutes(10),
            'tiger_reached_at' => $now->copy()->subMinutes(4),
            'completed_at' => $now->copy(),
        ]);

        // Tiger response time: inprogress (T-10m) to reached (T-4m) = 6m = 360s
        $this->assertEquals(360, $call->computed_tiger_response_time_sec);

        // Assistance duration: reached (T-4m) to completed (T-0) = 4m = 240s
        $this->assertEquals(240, $call->computed_assistance_duration_sec);
    }
}
