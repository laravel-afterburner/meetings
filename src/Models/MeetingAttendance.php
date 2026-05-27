<?php

namespace Afterburner\Meetings\Models;

use Afterburner\Meetings\Enums\AttendanceStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingAttendance extends Model
{
    protected $fillable = [
        'meeting_id',
        'user_id',
        'status',
        'recorded_by_user_id',
        'notes',
    ];

    protected $casts = [
        'status' => AttendanceStatus::class,
    ];

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }
}
