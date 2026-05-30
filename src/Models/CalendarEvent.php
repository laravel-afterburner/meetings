<?php

namespace Afterburner\Meetings\Models;

use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CalendarEvent extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'team_id',
        'created_by_user_id',
        'title',
        'description',
        'starts_at',
        'ends_at',
        'all_day',
        'location',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'all_day' => 'boolean',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function scopeForTeam($query, int $teamId)
    {
        return $query->where('team_id', $teamId);
    }

    public function scopeOverlapping($query, \DateTimeInterface $rangeStart, \DateTimeInterface $rangeEnd)
    {
        return $query
            ->where('starts_at', '<=', $rangeEnd)
            ->where('ends_at', '>=', $rangeStart);
    }
}
