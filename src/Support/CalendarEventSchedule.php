<?php

namespace Afterburner\Meetings\Support;

use App\Models\Team;
use App\Support\TeamDateTime;
use Carbon\Carbon;

class CalendarEventSchedule
{
    public static function syncedTimedEnd(Team $team, string $startsAtLocal, ?string $endsAtLocal): ?string
    {
        $start = TeamDateTime::fromDateTimeLocal($team, $startsAtLocal);

        if (! $start) {
            return $endsAtLocal;
        }

        $end = filled($endsAtLocal)
            ? TeamDateTime::fromDateTimeLocal($team, $endsAtLocal)
            : null;

        if (! $end || $end->lte($start)) {
            return TeamDateTime::toDateTimeLocal($team, $start->copy()->addHour());
        }

        return $endsAtLocal;
    }

    public static function syncedAllDayEnd(string $startDate, string $endDate): string
    {
        if ($endDate === '' || $endDate < $startDate) {
            return $startDate;
        }

        return $endDate;
    }

    public static function assertEndsAfterStart(Carbon $startsAt, Carbon $endsAt): void
    {
        if ($endsAt->lte($startsAt)) {
            throw new \InvalidArgumentException('The event end must be after the start.');
        }
    }
}
