<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class SurveyLocation extends Model
{
    public const STATUSES = ['draft', 'submitted', 'approved', 'rejected'];

    protected $fillable = [
        'uuid', 'category_id', 'name', 'address', 'lat', 'lng', 'phone_numbers', 'is_24h', 'opens_at', 'closes_at',
        'office_id', 'marker_id', 'km', 'carriageway', 'direction', 'geo_status', 'status', 'is_active',
        'surveyed_by', 'reviewed_by', 'reviewed_at', 'review_note',
    ];

    protected $casts = [
        'phone_numbers' => 'array', 'is_24h' => 'boolean', 'is_active' => 'boolean',
        'lat' => 'float', 'lng' => 'float', 'reviewed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $m) {
            $m->uuid = $m->uuid ?: (string) Str::uuid();
        });
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(SurveyCategory::class, 'category_id');
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(SurveyService::class, 'survey_location_service');
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class, 'office_id');
    }

    public function surveyor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'surveyed_by');
    }

    public function scopePublic(Builder $q): Builder
    {
        return $q->where('status', 'approved')->where('is_active', true);
    }

    /** Overnight hours are valid (closes < opens). */
    public function isOpenAt(\Carbon\CarbonInterface $at): bool
    {
        if ($this->is_24h) {
            return true;
        }
        if (! $this->opens_at || ! $this->closes_at) {
            return false;
        }
        $now = $at->format('H:i:s');
        $o = substr((string) $this->opens_at, 0, 8);
        $c = substr((string) $this->closes_at, 0, 8);

        return $o <= $c ? ($now >= $o && $now <= $c) : ($now >= $o || $now <= $c);
    }
}
