<?php

namespace Afterburner\Meetings\Support;

use App\Models\Team;
use App\Models\User;

final class CalendarFeedAccess
{
    public static function allows(User $user, Team $team): bool
    {
        if (! config('afterburner-meetings.calendar.enabled', true)) {
            return false;
        }

        if (! $user->belongsToTeam($team)) {
            return false;
        }

        if (! SubscriptionEntitlementGate::allows($team)) {
            return false;
        }

        return MeetingsPermissions::canViewSection($user, $team, MeetingsPermissions::SECTION_CALENDAR);
    }
}
