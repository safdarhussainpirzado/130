<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OfficerAvailabilityLog extends Model
{
    protected $table = 'officer_availability_log';

    protected $fillable = ['user_id', 'status', 'reason', 'started_at', 'ended_at', 'duration_sec'];

    protected $casts = ['started_at' => 'datetime', 'ended_at' => 'datetime'];
}
