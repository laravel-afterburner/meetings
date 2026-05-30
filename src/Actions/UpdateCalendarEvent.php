<?php

namespace Afterburner\Meetings\Actions;

use Afterburner\Meetings\Exceptions\MeetingsException;
use Afterburner\Meetings\Models\CalendarEvent;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Gate;

class UpdateCalendarEvent
{
    public function execute(
        CalendarEvent $event,
        User $user,
        string $title,
        Carbon $startsAt,
        Carbon $endsAt,
        bool $allDay = false,
        ?string $description = null,
        ?string $location = null,
    ): CalendarEvent {
        Gate::forUser($user)->authorize('update', $event);

        if ($endsAt->lte($startsAt)) {
            throw new MeetingsException('The event end must be after the start.');
        }

        $event->update([
            'title' => $title,
            'description' => $description,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'all_day' => $allDay,
            'location' => $location,
        ]);

        return $event->fresh();
    }
}
