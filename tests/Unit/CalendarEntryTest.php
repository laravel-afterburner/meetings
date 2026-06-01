<?php

namespace Afterburner\Meetings\Tests\Unit;

use Afterburner\Meetings\Support\CalendarEntry;
use Afterburner\Meetings\Tests\TestCase;
use Carbon\Carbon;

class CalendarEntryTest extends TestCase
{
    public function test_for_display_timezone_converts_entry_times(): void
    {
        $entry = new CalendarEntry(
            id: 'meeting-1',
            kind: 'meeting',
            title: 'Council meeting',
            startsAt: Carbon::parse('2026-06-15 17:30:00', 'America/Vancouver'),
            endsAt: Carbon::parse('2026-06-15 18:30:00', 'America/Vancouver'),
            allDay: false,
        );

        $display = $entry->forDisplayTimezone('America/Iqaluit');

        $this->assertSame(
            $display->startsAt->format('g:i A').' ('.$display->startsAt->format('T').')',
            $display->timeLabel()
        );
        $this->assertSame('2026-06-15', $display->startsAt->format('Y-m-d'));
    }

    public function test_for_display_timezone_keeps_all_day_events_on_same_dates(): void
    {
        $entry = new CalendarEntry(
            id: 'event-1',
            kind: 'event',
            title: 'Stat holiday',
            startsAt: Carbon::parse('2026-06-15 00:00:00', 'America/Vancouver'),
            endsAt: Carbon::parse('2026-06-15 23:59:59', 'America/Vancouver'),
            allDay: true,
        );

        $display = $entry->forDisplayTimezone('America/Iqaluit');

        $this->assertSame('2026-06-15', $display->startDate()->format('Y-m-d'));
        $this->assertSame('2026-06-15', $display->endDate()->format('Y-m-d'));
        $this->assertFalse($display->spansMultipleDays());
    }

    public function test_for_display_timezone_keeps_multi_day_all_day_events_on_same_dates(): void
    {
        $entry = new CalendarEntry(
            id: 'event-2',
            kind: 'event',
            title: 'Board retreat',
            startsAt: Carbon::parse('2026-06-10 00:00:00', 'America/Vancouver'),
            endsAt: Carbon::parse('2026-06-12 23:59:59', 'America/Vancouver'),
            allDay: true,
        );

        $display = $entry->forDisplayTimezone('America/Iqaluit');

        $this->assertSame('2026-06-10', $display->startDate()->format('Y-m-d'));
        $this->assertSame('2026-06-12', $display->endDate()->format('Y-m-d'));
        $this->assertSame(3, $display->daySpan());
    }
}
