<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SurveyService extends Model
{
    protected $fillable = ['category_id', 'name', 'icon', 'description', 'sort_order', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function category(): BelongsTo
    {
        return $this->belongsTo(SurveyCategory::class, 'category_id');
    }
}
