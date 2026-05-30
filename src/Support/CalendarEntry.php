<?php

namespace Afterburner\Meetings\Support;

use Afterburner\Meetings\Models\CalendarEvent;
use Afterburner\Meetings\Models\Meeting;
use App\Models\Team;
use Carbon\Carbon;

class CalendarEntry
{
    public function __construct(
        public string $id,
        public string $kind,
        public string $title,
        public Carbon $startsAt,
        public Carbon $endsAt,
        public bool $allDay,
        public ?string $location = null,
        public ?string $description = null,
        public ?string $url = null,
        public ?string $status = null,
    ) {}

    public static function fromCalendarEvent(CalendarEvent $event, Team $team): self
    {
        $startsAt = TeamDateTime::toTeamTimezone($team, $event->starts_at);
        $endsAt = TeamDateTime::toTeamTimezone($team, $event->ends_at);

        return new self(
            id: 'event-'.$event->id,
            kind: 'event',
            title: $event->title,
            startsAt: $startsAt,
            endsAt: $endsAt,
            allDay: $event->all_day,
            location: $event->location,
            description: $event->description,
        );
    }

    public static function fromMeeting(Meeting $meeting, Team $team): self
    {
        $startsAt = TeamDateTime::toTeamTimezone($team, $meeting->scheduled_at);
        $endsAt = $startsAt->copy()->addHour();

        return new self(
            id: 'meeting-'.$meeting->id,
            kind: 'meeting',
            title: $meeting->title,
            startsAt: $startsAt,
            endsAt: $endsAt,
            allDay: false,
            location: $meeting->location,
            description: $meeting->agenda_notes,
            url: route('teams.meetings.show', ['team' => $team->id, 'meeting' => $meeting->id]),
            status: $meeting->status->value,
        );
    }

    public function occursOn(Carbon $day): bool
    {
        $dayStart = $day->copy()->startOfDay();
        $dayEnd = $day->copy()->endOfDay();

        return $this->startsAt->lte($dayEnd) && $this->endsAt->gte($dayStart);
    }

    public function startDate(): Carbon
    {
        return $this->startsAt->copy()->startOfDay();
    }

    public function endDate(): Carbon
    {
        return $this->endsAt->copy()->startOfDay();
    }

    public function spansMultipleDays(): bool
    {
        return $this->startDate()->lt($this->endDate());
    }

    public function daySpan(): int
    {
        return $this->startDate()->diffInDays($this->endDate()) + 1;
    }

    public function showsInAllDayBar(): bool
    {
        return $this->allDay || $this->spansMultipleDays();
    }

    public function showsAsTimedBlock(): bool
    {
        return ! $this->allDay && ! $this->spansMultipleDays();
    }

    public function timeLabel(): string
    {
        return $this->startsAt->format('g:i A');
    }

    public function timeRangeLabel(): string
    {
        if ($this->allDay) {
            return 'All day';
        }

        if ($this->startsAt->eq($this->endsAt)) {
            return $this->timeLabel();
        }

        return $this->startsAt->format('g:i A').' – '.$this->endsAt->format('g:i A');
    }

    public function effectiveStartsAtOn(Carbon $day): Carbon
    {
        return $this->startsAt->lt($day) ? $day->copy()->startOfDay() : $this->startsAt->copy();
    }

    public function effectiveEndsAtOn(Carbon $day): Carbon
    {
        $dayEnd = $day->copy()->endOfDay();

        return $this->endsAt->gt($dayEnd) ? $dayEnd : $this->endsAt->copy();
    }
}
