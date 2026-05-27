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
        $this->assertStringContainsString('10:00 AM', $formatted);
    }
}
