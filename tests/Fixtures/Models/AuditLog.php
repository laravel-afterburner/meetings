<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $fillable = [
        'user_id',
        'impersonated_by',
        'action_type',
        'category',
        'event_name',
        'auditable_type',
        'auditable_id',
        'team_id',
        'changes',
        'metadata',
        'request_id',
    ];

    protected $casts = [
        'changes' => 'array',
        'metadata' => 'array',
    ];
}
