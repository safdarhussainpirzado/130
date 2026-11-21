<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SurveyCategory extends Model
{
    protected $fillable = ['name', 'icon', 'description', 'sort_order', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function services(): HasMany
    {
        return $this->hasMany(SurveyService::class, 'category_id')->orderBy('sort_order')->orderBy('name');
    }

    public function locations(): HasMany
    {
        return $this->hasMany(SurveyLocation::class, 'category_id');
    }
}
