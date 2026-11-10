<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalyticsOutboxEvent extends Model
{
    use HasFactory;

    protected $table = 'analytics_outbox_events';

    protected $fillable = [
        'event_uuid',
        'call_id',
        'event_type',
        'stat_date',
        'stat_hour',
        'old_zone_id',
        'old_sector_id',
        'old_beat_id',
        'new_zone_id',
        'new_sector_id',
        'new_beat_id',
        'old_status',
        'new_status',
        'call_type_id',
        'call_category',
        'priority',
        'response_time_sec',
        'resolution_time_sec',
        'is_sla_met',
        'is_sla_eligible',
        'payload',
        'status',
        'attempts',
        'processed_at',
        'error_message',
    ];

    protected $casts = [
        'stat_date'           => 'date:Y-m-d',
        'stat_hour'           => 'integer',
        'old_zone_id'         => 'integer',
        'old_sector_id'       => 'integer',
        'old_beat_id'         => 'integer',
        'new_zone_id'         => 'integer',
        'new_sector_id'       => 'integer',
        'new_beat_id'         => 'integer',
        'call_type_id'        => 'integer',
        'priority'            => 'integer',
        'response_time_sec'   => 'integer',
        'resolution_time_sec' => 'integer',
        'is_sla_met'          => 'boolean',
        'is_sla_eligible'     => 'boolean',
        'payload'             => 'array',
        'attempts'            => 'integer',
        'processed_at'        => 'datetime',
    ];

    public function call(): BelongsTo
    {
        return $this->belongsTo(Call::class);
    }
}
