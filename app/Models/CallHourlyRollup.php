<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CallHourlyRollup extends Model
{
    use HasFactory;

    protected $table = 'call_hourly_rollups';

    protected $fillable = [
        'stat_date',
        'stat_hour',
        'zone_id',
        'sector_id',
        'beat_id',
        'call_type_id',
        'call_category',
        'priority',
        'status',
        'total_calls',
        'completed_calls',
        'pending_calls',
        'sum_response_seconds',
        'response_count',
        'sum_resolution_seconds',
        'resolution_count',
        'sla_met_count',
        'sla_eligible_count',
    ];

    protected $casts = [
        'stat_date'               => 'date:Y-m-d',
        'stat_hour'               => 'integer',
        'zone_id'                 => 'integer',
        'sector_id'               => 'integer',
        'beat_id'                 => 'integer',
        'call_type_id'            => 'integer',
        'priority'                => 'integer',
        'total_calls'             => 'integer',
        'completed_calls'         => 'integer',
        'pending_calls'           => 'integer',
        'sum_response_seconds'    => 'integer',
        'response_count'          => 'integer',
        'sum_resolution_seconds'  => 'integer',
        'resolution_count'        => 'integer',
        'sla_met_count'           => 'integer',
        'sla_eligible_count'      => 'integer',
    ];

    /**
     * Calculate exact weighted average response time in seconds.
     */
    public function getAvgResponseSecondsAttribute(): float
    {
        return $this->response_count > 0
            ? round($this->sum_response_seconds / $this->response_count, 1)
            : 0.0;
    }

    /**
     * Calculate exact weighted average resolution time in seconds.
     */
    public function getAvgResolutionSecondsAttribute(): float
    {
        return $this->resolution_count > 0
            ? round($this->sum_resolution_seconds / $this->resolution_count, 1)
            : 0.0;
    }

    /**
     * Calculate SLA compliance percentage.
     */
    public function getSlaPercentageAttribute(): float
    {
        return $this->sla_eligible_count > 0
            ? round(($this->sla_met_count / $this->sla_eligible_count) * 100, 1)
            : 0.0;
    }
}
