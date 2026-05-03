<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Designation extends Model
{
    use LogsActivity;

    protected $fillable = ['name', 'short_code', 'bps', 'similar_rank', 'type', 'sort_order', 'is_field', 'is_active'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }
}
