<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CallEvidence extends Model
{
    protected $table = 'call_evidence';

    protected $fillable = ['call_id', 'path', 'mime', 'size_bytes', 'uploaded_by', 'uploaded_at'];

    protected $casts = ['uploaded_at' => 'datetime'];

    public function call(): BelongsTo
    {
        return $this->belongsTo(Call::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
