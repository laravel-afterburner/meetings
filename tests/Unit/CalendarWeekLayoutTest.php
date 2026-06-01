<?php

namespace Afterburner\Meetings\Tests\Unit;

use Afterburner\Meetings\Support\CalendarEntry;
use Afterburner\Meetings\Support\CalendarWeekLayout;
use Afterburner\Meetings\Tests\TestCase;
use Carbon\Carbon;

class CalendarWeekLayoutTest extends TestCase
{
    /**
     * @return \Illuminate\Support\Collection<int, array{date: string, day: int, inMonth: bool, isToday: bool}>
     */
    protected function sampleWeekDays(): \Illuminate\Support\Collection
    {
        return collect([
            ['date' => '2026-06-08', 'day' => 8, 'inMonth' => true, 'isToday' => false],
            ['date' => '2026-06-09', 'day' => 9, 'inMonth' => true, 'isToday' => false],
            ['date' => '2026-06-10', 'day' => 10, 'inMonth' => true, 'isToday' => false],
            ['date' => '2026-06-11', 'day' => 11, 'inMonth' => true, 'isToday' => false],
            ['date' => '2026-06-12', 'day' => 12, 'inMonth' => true, 'isToday' => false],
            ['date' => '2026-06-13', 'day' => 13, 'inMonth' => true, 'isToday' => false],
            ['date' => '2026-06-14', 'day' => 14, 'inMonth' => true, 'isToday' => false],
        ]);
    }

    public function test_multi_day_event_renders_as_one_bar_segment_per_week(): void
    {
        $entry = new CalendarEntry(
            id: 'event-1',
            kind: 'event',
            title: 'Board retreat',
            startsAt: Carbon::parse('2026-06-10 00:00:00'),
            endsAt: Carbon::parse('2026-06-12 23:59:59'),
            allDay: true,
        );

        $layout = app(CalendarWeekLayout::class)->layout($this->sampleWeekDays(), collect([$entry]));

        $this->assertCount(1, $layout['bars']);
        $this->assertSame(2, $layout['bars'][0]['startCol']);
        $this->assertSame(4, $layout['bars'][0]['endCol']);
        $this->assertTrue($layout['bars'][0]['segmentStart']);
        $this->assertTrue($layout['bars'][0]['segmentEnd']);

        foreach ($layout['days'] as $day) {
            $this->assertCount(0, $day['timedEntries']);
        }
    }

    public function test_single_day_timed_events_include_time_labels(): void
    {
        $entry = new CalendarEntry(
            id: 'event-2',
            kind: 'event',
            title: 'Paint day',
            startsAt: Carbon::parse('2026-06-10 09:00:00'),
            endsAt: Carbon::parse('2026-06-10 12:00:00'),
            allDay: false,
        );

        $layout = app(CalendarWeekLayout::class)->layout($this->sampleWeekDays(), collect([$entry]));

        $this->assertCount(0, $layout['bars']);
        $this->assertCount(1, $layout['days'][2]['timedEntries']);
        $timed = $layout['days'][2]['timedEntries'][0];
        $this->assertSame($entry->timeLabel(), $timed['timeLabel']);
        $this->assertSame($entry->timeRangeLabel(), $timed['timeRangeLabel']);
    }

    public function test_overlapping_timed_events_use_side_by_side_columns(): void
    {
        $first = new CalendarEntry(
            id: 'event-a',
            kind: 'event',
            title: 'Site walk',
            startsAt: Carbon::parse('2026-06-10 09:00:00'),
            endsAt: Carbon::parse('2026-06-10 10:00:00'),
            allDay: false,
        );

        $second = new CalendarEntry(
            id: 'event-b',
            kind: 'event',
            title: 'Inspection',
            startsAt: Carbon::parse('2026-06-10 09:30:00'),
            endsAt: Carbon::parse('2026-06-10 10:30:00'),
            allDay: false,
        );

        $layout = app(CalendarWeekLayout::class)->layout($this->sampleWeekDays(), collect([$first, $second]));
        $timed = $layout['days'][2]['timedEntries'];

        $this->assertCount(2, $timed);
        $this->assertSame(2, $timed[0]['columnCount']);
        $this->assertSame(0, $timed[0]['column']);
        $this->assertSame(1, $timed[1]['column']);
        $this->assertSame(0, $timed[0]['lane']);
        $this->assertSame(0, $timed[1]['lane']);
    }

    public function test_non_overlapping_timed_events_stack_in_separate_rows(): void
    {
        $morning = new CalendarEntry(
            id: 'event-morning',
            kind: 'event',
            title: 'Morning briefing',
            startsAt: Carbon::parse('2026-06-10 09:00:00'),
            endsAt: Carbon::parse('2026-06-10 10:00:00'),
            allDay: false,
        );

        $afternoon = new CalendarEntry(
            id: 'event-afternoon',
            kind: 'event',
            title: 'Budget review',
            startsAt: Carbon::parse('2026-06-10 14:00:00'),
            endsAt: Carbon::parse('2026-06-10 15:00:00'),
            allDay: false,
        );

        $layout = app(CalendarWeekLayout::class)->layout($this->sampleWeekDays(), collect([$morning, $afternoon]));
        $timed = $layout['days'][2]['timedEntries'];

        $this->assertCount(2, $timed);
        $this->assertSame(0, $timed[0]['lane']);
        $this->assertSame(1, $timed[1]['lane']);
        $this->assertSame(2, $layout['days'][2]['timedLaneCount']);
    }

    public function test_single_day_all_day_events_render_in_bar_row(): void
    {
        $entry = new CalendarEntry(
            id: 'event-holiday',
            kind: 'event',
            title: 'Stat holiday',
            startsAt: Carbon::parse('2026-06-10 00:00:00'),
            endsAt: Carbon::parse('2026-06-10 23:59:59'),
            allDay: true,
        );

        $layout = app(CalendarWeekLayout::class)->layout($this->sampleWeekDays(), collect([$entry]));

        $this->assertCount(1, $layout['bars']);
        $this->assertSame(2, $layout['bars'][0]['startCol']);
        $this->assertSame(2, $layout['bars'][0]['endCol']);
        $this->assertCount(0, $layout['days'][2]['timedEntries']);
    }
}
