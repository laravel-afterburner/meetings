<?php

namespace Afterburner\Meetings\Models;

use Afterburner\Meetings\Enums\ActionItemStatus;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingActionItem extends Model
{
    protected $fillable = [
        'meeting_id',
        'team_id',
        'title',
        'description',
        'assigned_to_user_id',
        'due_at',
        'status',
        'created_by_user_id',
        'completed_at',
        'assignee_notified_at',
        'assignee_notification_id',
        'sort_order',
    ];

    protected $casts = [
        'status' => ActionItemStatus::class,
        'due_at' => 'datetime',
        'completed_at' => 'datetime',
        'assignee_notified_at' => 'datetime',
    ];

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function scopeForTeam($query, int $teamId)
    {
        return $query->where('team_id', $teamId);
    }

    public function scopeAssignedTo($query, int $userId)
    {
        return $query->where('assigned_to_user_id', $userId);
    }

    public function scopeOverdue($query)
    {
        return $query
            ->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->whereIn('status', [ActionItemStatus::Open, ActionItemStatus::InProgress]);
    }

    public function isOverdue(): bool
    {
        return $this->due_at !== null
            && $this->due_at->isPast()
            && $this->status->isOpen();
    }

    public function isAssignedTo(User $user): bool
    {
        return $this->assigned_to_user_id === $user->id;
    }
}
