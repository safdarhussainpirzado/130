<?php

namespace Tests\Feature\Analytics;

use App\Models\AnalyticsOutboxEvent;
use App\Services\Analytics\CallRollupService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Disposable test DB only. Each call produces several outbox events; counters must move once per transition. */
class RollupTransitionTest extends TestCase
{
    use DatabaseTransactions;

    private function apply(string $type, ?string $old, string $new, bool $eligible, ?bool $met): void
    {
        $e = new AnalyticsOutboxEvent([
            'event_type' => $type, 'stat_date' => '2031-01-05', 'stat_hour' => 12,
            'old_zone_id' => 901, 'old_sector_id' => 902, 'old_beat_id' => 903,
            'new_zone_id' => 901, 'new_sector_id' => 902, 'new_beat_id' => 903,
            'old_status' => $old, 'new_status' => $new, 'call_type_id' => 2, 'call_category' => 'general_help',
            'priority' => 3, 'response_time_sec' => $new === 'completed' ? 100 : null,
            'resolution_time_sec' => $new === 'completed' ? 600 : null,
            'is_sla_eligible' => $eligible, 'is_sla_met' => $met,
        ]);
        $m = new \ReflectionMethod(CallRollupService::class, 'applyEventToRollup');
        $m->setAccessible(true);
        $m->invoke(app(CallRollupService::class), $e);
    }

    private function totals(): object
    {
        return DB::table('call_hourly_rollups')->where('stat_date', '2031-01-05')->where('beat_id', 903)
            ->selectRaw('COALESCE(SUM(total_calls),0) t, COALESCE(SUM(completed_calls),0) c, COALESCE(SUM(pending_calls),0) p, COALESCE(SUM(sla_eligible_count),0) e, COALESCE(SUM(sla_met_count),0) m, COALESCE(SUM(resolution_count),0) rc')
            ->first();
    }

    public function test_full_lifecycle_counts_each_call_once(): void
    {
        $this->apply('call_created', null, 'pending', true, null);
        $this->apply('status_changed', 'pending', 'in_progress', true, null);
        $this->apply('status_changed', 'in_progress', 'tiger_reached', true, null);
        $this->assertSame(1, (int) $this->totals()->p, 'one open call -> pending 1, not 3');
        $this->apply('status_changed', 'tiger_reached', 'completed', true, true);
        $t = $this->totals();
        $this->assertSame([1, 1, 0, 1, 1, 1], [(int) $t->t, (int) $t->c, (int) $t->p, (int) $t->e, (int) $t->m, (int) $t->rc]);
    }

    public function test_repeat_event_on_same_status_changes_nothing(): void
    {
        $this->apply('call_created', null, 'pending', true, null);
        $this->apply('status_changed', 'pending', 'completed', true, true);
        $this->apply('status_changed', 'completed', 'completed', true, true); // e.g. remarks edited after completion
        $t = $this->totals();
        $this->assertSame([1, 1, 0, 1, 1], [(int) $t->t, (int) $t->c, (int) $t->p, (int) $t->e, (int) $t->m]);
    }

    public function test_ineligible_junk_never_counts_toward_sla(): void
    {
        $this->apply('call_created', null, 'completed', false, null);
        $t = $this->totals();
        $this->assertSame([1, 1, 0, 0, 0], [(int) $t->t, (int) $t->c, (int) $t->p, (int) $t->e, (int) $t->m]);
    }
}
