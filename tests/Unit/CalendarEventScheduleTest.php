<?php

namespace Afterburner\Meetings\Tests\Unit;

use Afterburner\Meetings\Support\CalendarEventSchedule;
use Afterburner\Meetings\Tests\TestCase;
use App\Models\Team;

class CalendarEventScheduleTest extends TestCase
{
    public function test_synced_timed_end_defaults_to_one_hour_after_start(): void
    {
        [, $team] = $this->createTeamWithUser(['manage_meetings']);

        $endsAtLocal = CalendarEventSchedule::syncedTimedEnd(
            $team,
            '2026-05-30T09:00',
            null,
        );

        $this->assertSame('2026-05-30T10:00', $endsAtLocal);
    }

    public function test_synced_timed_end_moves_forward_when_start_date_changes(): void
    {
        [, $team] = $this->createTeamWithUser(['manage_meetings']);

        $endsAtLocal = CalendarEventSchedule::syncedTimedEnd(
            $team,
            '2026-06-01T09:00',
            '2026-05-30T10:00',
        );

        $this->assertSame('2026-06-01T10:00', $endsAtLocal);
    }

    public function test_synced_timed_end_keeps_valid_end_when_still_after_start(): void
    {
        [, $team] = $this->createTeamWithUser(['manage_meetings']);

        $endsAtLocal = CalendarEventSchedule::syncedTimedEnd(
            $team,
            '2026-05-30T09:00',
            '2026-05-30T11:30',
        );

        $this->assertSame('2026-05-30T11:30', $endsAtLocal);
    }

    public function test_synced_all_day_end_moves_to_start_when_earlier(): void
    {
        $this->assertSame(
            '2026-06-01',
            CalendarEventSchedule::syncedAllDayEnd('2026-06-01', '2026-05-30'),
        );
    }

    public function test_synced_all_day_end_keeps_later_end_when_start_moves_forward(): void
    {
        $this->assertSame(
            '2026-06-20',
            CalendarEventSchedule::syncedAllDayEnd('2026-06-20', '2026-06-20'),
        );
    }
}
