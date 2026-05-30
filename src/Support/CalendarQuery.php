<?php

namespace Afterburner\Meetings\Support;

use Afterburner\Meetings\Enums\MeetingStatus;
use Afterburner\Meetings\Models\CalendarEvent;
use Afterburner\Meetings\Models\Meeting;
use App\Models\Team;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class CalendarQuery
{
    /**
     * @return Collection<int, CalendarEntry>
     */
    public function entriesForRange(Team $team, Carbon $rangeStart, Carbon $rangeEnd): Collection
    {
        $utcStart = $rangeStart->copy()->startOfDay()->utc();
        $utcEnd = $rangeEnd->copy()->endOfDay()->utc();

        $events = CalendarEvent::query()
            ->forTeam($team->id)
            ->overlapping($utcStart, $utcEnd)
            ->orderBy('starts_at')
            ->get()
            ->map(fn (CalendarEvent $event) => CalendarEntry::fromCalendarEvent($event, $team));

        $meetings = Meeting::query()
            ->forTeam($team->id)
            ->whereNotNull('scheduled_at')
            ->whereNotIn('status', [MeetingStatus::Draft])
            ->where('scheduled_at', '>=', $utcStart)
            ->where('scheduled_at', '<=', $utcEnd)
            ->orderBy('scheduled_at')
            ->get()
            ->map(fn (Meeting $meeting) => CalendarEntry::fromMeeting($meeting, $team));

        return $events
            ->concat($meetings)
            ->sortBy(fn (CalendarEntry $entry) => [$entry->allDay ? 0 : 1, $entry->startsAt->timestamp, $entry->title])
            ->values();
    }
}
