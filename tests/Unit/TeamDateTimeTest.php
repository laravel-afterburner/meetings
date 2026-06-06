<?php

namespace Afterburner\Meetings\Tests\Unit;

use Afterburner\Meetings\Support\TeamDateTime;
use Afterburner\Meetings\Tests\TestCase;
use Carbon\Carbon;

class TeamDateTimeTest extends TestCase
{
    public function test_from_and_to_datetime_local_use_team_timezone(): void
    {
        config(['app.timezone' => 'UTC']);

        [, $team] = $this->createTeamWithUser();
        $team->update(['timezone' => 'America/Vancouver']);

        $utc = Carbon::parse('2026-06-15 17:00:00', 'UTC');

        $local = TeamDateTime::toDateTimeLocal($team, $utc);
        $this->assertSame('2026-06-15T10:00', $local);

        $parsed = TeamDateTime::fromDateTimeLocal($team, '2026-06-15T10:00');
        $this->assertSame('2026-06-15 17:00:00', $parsed->utc()->format('Y-m-d H:i:s'));
    }

    public function test_format_displays_in_team_timezone(): void
    {
        config(['app.timezone' => 'UTC']);

        [, $team] = $this->createTeamWithUser();
        $team->update(['timezone' => 'America/Vancouver']);

        $formatted = TeamDateTime::format($team, Carbon::parse('2026-06-15 17:00:00', 'UTC'));

        $this->assertStringContainsString('Jun 15, 2026', $formatted);
        $this->assertStringContainsString('10:00 AM (PDT)', $formatted);
    }

    public function test_datetime_local_converts_between_team_and_user_timezones(): void
    {
        config(['app.timezone' => 'UTC']);

        [$user, $team] = $this->createTeamWithUser();
        $team->update(['timezone' => 'America/Vancouver']);
        $user->update(['timezone' => 'America/Iqaluit']);

        $this->actingAs($user);

        // 5:30 PM Vancouver on June 15 = 8:30 PM Iqaluit (3 hours ahead).
        $utc = Carbon::parse('2026-06-16 00:30:00', 'UTC');

        $this->assertSame('2026-06-15T20:30', TeamDateTime::toDateTimeLocal($team, $utc));
        $this->assertSame('America/Iqaluit', TeamDateTime::datetimeLocalTimezone($team));
        $this->assertSame('America/Vancouver', TeamDateTime::datetimeLocalTeamTimezoneHint($team));

        $parsed = TeamDateTime::fromDateTimeLocal($team, '2026-06-15T20:30');
        $this->assertSame('2026-06-16 00:30:00', $parsed->utc()->format('Y-m-d H:i:s'));
    }

    public function test_format_display_carbon_uses_core_helper_shape(): void
    {
        config(['app.timezone' => 'UTC']);

        [, $team] = $this->createTeamWithUser();
        $team->update(['timezone' => 'America/Vancouver']);

        $carbon = TeamDateTime::toTeamTimezone($team, Carbon::parse('2026-06-15 17:00:00', 'UTC'));

        $formatted = TeamDateTime::formatDisplayCarbon($carbon);

        $this->assertStringContainsString('Jun 15, 2026', $formatted);
        $this->assertStringContainsString('10:00 AM', $formatted);
        $this->assertStringContainsString('(PDT)', $formatted);
    }

    public function test_format_calendar_entry_schedule_uses_core_helper_for_all_day_events(): void
    {
        config(['app.timezone' => 'UTC']);

        $startsAt = Carbon::parse('2026-06-10', 'UTC');
        $endsAt = Carbon::parse('2026-06-12', 'UTC');

        $formatted = TeamDateTime::formatCalendarEntrySchedule($startsAt, $endsAt, true);

        $this->assertStringStartsWith('All day ·', $formatted);
        $this->assertStringContainsString('Jun 10, 2026', $formatted);
        $this->assertStringContainsString('Jun 12, 2026', $formatted);
    }

    public function test_calendar_defaults_to_user_timezone_when_it_differs_from_team(): void
    {
        config(['app.timezone' => 'UTC']);

        [$user, $team] = $this->createTeamWithUser();
        $team->update(['timezone' => 'America/Vancouver']);
        $user->update(['timezone' => 'America/Iqaluit']);

        $this->actingAs($user);

        $this->assertTrue(TeamDateTime::canChooseCalendarDisplayTimezone($team));
        $this->assertSame(TeamDateTime::CALENDAR_DISPLAY_USER, TeamDateTime::defaultCalendarDisplayMode($team));
        $this->assertSame(
            'America/Iqaluit',
            TeamDateTime::resolveCalendarDisplayTimezone($team, TeamDateTime::CALENDAR_DISPLAY_USER)
        );
    }
}
