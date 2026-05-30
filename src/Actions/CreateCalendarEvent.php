<?php

namespace Afterburner\Meetings\Actions;

use Afterburner\Meetings\Exceptions\MeetingsException;
use Afterburner\Meetings\Models\CalendarEvent;
use App\Models\Team;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Gate;

class CreateCalendarEvent
{
    public function execute(
        Team $team,
        User $user,
        string $title,
        Carbon $startsAt,
        Carbon $endsAt,
        bool $allDay = false,
        ?string $description = null,
        ?string $location = null,
    ): CalendarEvent {
        Gate::forUser($user)->authorize('create', [CalendarEvent::class, $team]);

        if ($team->id !== $user->currentTeam?->id && ! $user->belongsToTeam($team)) {
            throw new MeetingsException('You do not belong to this team.');
        }

        if ($endsAt->lte($startsAt)) {
            throw new MeetingsException('The event end must be after the start.');
        }

        return CalendarEvent::query()->create([
            'team_id' => $team->id,
            'created_by_user_id' => $user->id,
            'title' => $title,
            'description' => $description,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'all_day' => $allDay,
            'location' => $location,
        ]);
    }
}
