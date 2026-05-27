<?php

namespace Afterburner\Meetings\Tests\Feature;

use Afterburner\Meetings\Tests\TestCase;
use Illuminate\Support\Facades\Route;

class MeetingCreateRouteTest extends TestCase
{
    public function test_create_route_matches_before_meeting_show_route(): void
    {
        [$user, $team] = $this->createTeamWithUser(['manage_meetings']);

        $matched = Route::getRoutes()->match(
            \Illuminate\Http\Request::create("/teams/{$team->id}/meetings/create", 'GET')
        );

        $this->assertSame('teams.meetings.create', $matched->getName());
    }
}
