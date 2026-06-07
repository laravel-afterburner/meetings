<?php

namespace Afterburner\Meetings\Support;

use App\Models\Team;
use App\Support\TeamDateTime;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class IcsCalendarExporter
{
    /**
     * @param  Collection<int, CalendarEntry>  $entries
     */
    public function export(Team $team, Collection $entries, string $calendarName): string
    {
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Afterburner//Meetings Calendar//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:'.$this->escapeText($calendarName),
            'X-WR-TIMEZONE:'.TeamDateTime::teamTimezone($team),
        ];

        foreach ($entries as $entry) {
            $lines = array_merge($lines, $this->eventLines($entry, $team));
        }

        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", $lines)."\r\n";
    }

    /**
     * @return array<int, string>
     */
    protected function eventLines(CalendarEntry $entry, Team $team): array
    {
        $uid = $entry->id.'@afterburner-meetings';
        $summary = $this->escapeText($entry->title);
        $description = $entry->description ? $this->escapeText($entry->description) : null;
        $location = $entry->location ? $this->escapeText($entry->location) : null;

        $lines = [
            'BEGIN:VEVENT',
            'UID:'.$uid,
            'DTSTAMP:'.$this->formatUtc(now()),
            'SUMMARY:'.$summary,
        ];

        if ($entry->allDay) {
            $lines[] = 'DTSTART;VALUE=DATE:'.$entry->startsAt->format('Ymd');
            $lines[] = 'DTEND;VALUE=DATE:'.$entry->endsAt->copy()->addDay()->format('Ymd');
        } else {
            $lines[] = 'DTSTART:'.$this->formatUtc($entry->startsAt->copy()->utc());
            $lines[] = 'DTEND:'.$this->formatUtc($entry->endsAt->copy()->utc());
        }

        if ($description) {
            $lines[] = 'DESCRIPTION:'.$description;
        }

        if ($location) {
            $lines[] = 'LOCATION:'.$location;
        }

        if ($entry->url) {
            $lines[] = 'URL:'.$entry->url;
        }

        $lines[] = 'END:VEVENT';

        return $lines;
    }

    protected function formatUtc(Carbon $dateTime): string
    {
        return $dateTime->copy()->utc()->format('Ymd\THis\Z');
    }

    protected function escapeText(string $value): string
    {
        return str_replace(
            ['\\', ';', ',', "\n", "\r"],
            ['\\\\', '\;', '\,', '\n', ''],
            $value
        );
    }
}
