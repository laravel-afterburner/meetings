<?php

namespace Afterburner\Meetings\Actions;

use Afterburner\Meetings\Models\CalendarEvent;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class DeleteCalendarEvent
{
    public function execute(CalendarEvent $event, User $user): void
    {
        Gate::forUser($user)->authorize('delete', $event);

        $event->delete();
    }
}
