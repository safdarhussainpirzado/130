<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $fillable = [
        'user_id',
        'action',
        'table_name',
        'record_id',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'old_values' => 'json',
        'new_values' => 'json',
    ];

    protected $appends = [
        'event',
        'subject_type',
        'subject_id',
        'causer',
        'properties',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getEventAttribute()
    {
        return $this->action;
    }

    public function getSubjectTypeAttribute()
    {
        return $this->table_name;
    }

    public function getSubjectIdAttribute()
    {
        return $this->record_id;
    }

    public function getCauserAttribute()
    {
        return $this->user;
    }

    public function getPropertiesAttribute()
    {
        return [
            'ip'         => $this->ip_address,
            'user_agent' => $this->user_agent,
            'old'        => $this->old_values ?? [],
            'attributes' => $this->new_values ?? [],
        ];
    }

    const UPDATED_AT = null;
}
