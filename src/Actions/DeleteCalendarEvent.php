<?php

namespace Afterburner\Meetings\Actions;

use Afterburner\Meetings\Models\CalendarEvent;
use Afterburner\Meetings\Support\MeetingsAuditLogger;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class DeleteCalendarEvent
{
    public function execute(CalendarEvent $event, User $user): void
    {
        Gate::forUser($user)->authorize('delete', $event);

        MeetingsAuditLogger::calendarEventDeleted($event, $user);

        $event->delete();
    }
}
