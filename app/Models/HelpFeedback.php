<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HelpFeedback extends Model
{
    protected $table = 'help_feedback';

    protected $fillable = ['call_id', 'commuter_id', 'officer_id', 'timeliness', 'behaviour', 'helpfulness', 'overall', 'comment', 'submitted_at'];

    protected $casts = ['submitted_at' => 'datetime'];

    public function call(): BelongsTo
    {
        return $this->belongsTo(Call::class);
    }

    public function officer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'officer_id');
    }
}
