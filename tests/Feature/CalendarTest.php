<?php

namespace Afterburner\Meetings\Tests\Feature;

use Afterburner\Meetings\Actions\CreateCalendarEvent;
use Afterburner\Meetings\Actions\CreateMeeting;
use Afterburner\Meetings\Enums\MeetingStatus;
use Afterburner\Meetings\Enums\MeetingType;
use Afterburner\Meetings\Models\CalendarEvent;
use Afterburner\Meetings\Support\CalendarFeedToken;
use Afterburner\Meetings\Support\CalendarQuery;
use Afterburner\Meetings\Tests\TestCase;
use Carbon\Carbon;
use Illuminate\Support\Facades\Route;

class CalendarTest extends TestCase
{
    public function test_calendar_route_is_registered_before_meeting_show_route(): void
    {
        [$user, $team] = $this->createTeamWithUser(['manage_meetings']);

        $matched = Route::getRoutes()->match(
            \Illuminate\Http\Request::create("/teams/{$team->id}/meetings/calendar", 'GET')
        );

        $this->assertSame('teams.meetings.calendar', $matched->getName());
    }

    public function test_calendar_event_can_span_multiple_days(): void
    {
        [$user, $team] = $this->createTeamWithUser(['manage_meetings']);

        $startsAt = Carbon::parse('2026-06-10 00:00:00', 'UTC');
        $endsAt = Carbon::parse('2026-06-12 23:59:59', 'UTC');

        app(CreateCalendarEvent::class)->execute(
            $team,
            $user,
            'Board retreat',
            $startsAt,
            $endsAt,
            true,
        );

        $entries = app(CalendarQuery::class)->entriesForRange(
            $team,
            Carbon::parse('2026-06-01', 'UTC'),
            Carbon::parse('2026-06-30', 'UTC'),
        );

        $this->assertCount(1, $entries);
        $this->assertTrue($entries->first()->occursOn(Carbon::parse('2026-06-11', 'UTC')));
    }

    public function test_scheduled_meetings_appear_on_calendar_query(): void
    {
        [$user, $team] = $this->createTeamWithUser(['manage_meetings']);

        $meeting = app(CreateMeeting::class)->execute(
            $team,
            $user,
            'Council meeting',
            MeetingType::Council,
            scheduledAt: now()->addDays(3),
        );

        app(\Afterburner\Meetings\Actions\UpdateMeeting::class)->execute(
            $meeting,
            $user,
            $meeting->title,
            $meeting->type,
            MeetingStatus::Scheduled,
            scheduledAt: $meeting->scheduled_at,
        );

        $entries = app(CalendarQuery::class)->entriesForRange(
            $team,
            now()->startOfMonth(),
            now()->addMonths(2)->endOfMonth(),
        );

        $this->assertTrue(
            $entries->contains(fn ($entry) => $entry->kind === 'meeting' && $entry->title === 'Council meeting')
        );
    }

    public function test_calendar_feed_returns_ics_document(): void
    {
        [$user, $team] = $this->createTeamWithUser(['manage_meetings']);

        app(CreateCalendarEvent::class)->execute(
            $team,
            $user,
            'Community BBQ',
            now()->addWeek()->startOfDay()->utc(),
            now()->addWeek()->endOfDay()->utc(),
            true,
        );

        $url = CalendarFeedToken::feedUrl($user, $team);

        $response = $this->get($url);

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/calendar; charset=utf-8');
        $this->assertStringContainsString('BEGIN:VCALENDAR', $response->getContent());
        $this->assertStringContainsString('Community BBQ', $response->getContent());
    }

    public function test_calendar_feed_rejects_invalid_token(): void
    {
        [$user, $team] = $this->createTeamWithUser(['manage_meetings']);

        $response = $this->get(route('teams.meetings.calendar.feed', [
            'team' => $team->id,
            'token' => 'invalid-token',
        ]));

        $response->assertNotFound();
    }

    public function test_user_without_manage_meetings_cannot_create_calendar_events(): void
    {
        [$manager, $team] = $this->createTeamWithUser(['manage_meetings']);
        $member = $this->createAdditionalUser($team, [], 'viewer@example.com');

        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);

        app(CreateCalendarEvent::class)->execute(
            $team,
            $member,
            'Should fail',
            now()->utc(),
            now()->addHour()->utc(),
        );
    }

    public function test_calendar_event_is_persisted(): void
    {
        [$user, $team] = $this->createTeamWithUser(['manage_meetings']);

        $event = app(CreateCalendarEvent::class)->execute(
            $team,
            $user,
            'Paint day',
            now()->addDays(2)->utc(),
            now()->addDays(2)->addHours(3)->utc(),
        );

        $this->assertDatabaseHas('calendar_events', [
            'id' => $event->id,
            'team_id' => $team->id,
            'title' => 'Paint day',
        ]);
    }

    public function test_create_calendar_event_rejects_end_on_or_before_start(): void
    {
        [$user, $team] = $this->createTeamWithUser(['manage_meetings']);
        $startsAt = now()->addDay()->utc();

        $this->expectException(\Afterburner\Meetings\Exceptions\MeetingsException::class);

        app(CreateCalendarEvent::class)->execute(
            $team,
            $user,
            'Invalid range',
            $startsAt,
            $startsAt->copy(),
        );
    }
}
