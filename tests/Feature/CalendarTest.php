<?php

namespace Afterburner\Meetings\Tests\Feature;

use Afterburner\Meetings\Actions\CreateCalendarEvent;
use Afterburner\Meetings\Actions\CreateMeeting;
use Afterburner\Meetings\Actions\UpdateMeeting;
use Afterburner\Meetings\Enums\MeetingStatus;
use Afterburner\Meetings\Enums\MeetingType;
use Afterburner\Meetings\Exceptions\MeetingsException;
use Afterburner\Meetings\Livewire\Meetings\Calendar;
use Afterburner\Meetings\Models\CalendarEvent;
use Afterburner\Meetings\Models\Meeting;
use Afterburner\Meetings\Support\CalendarFeedToken;
use Afterburner\Meetings\Support\CalendarQuery;
use Afterburner\Meetings\Support\TeamDateTime;
use Afterburner\Meetings\Tests\TestCase;
use App\Models\Team;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

class CalendarTest extends TestCase
{
    public function test_next_month_navigates_from_may_to_june(): void
    {
        [$user, $team] = $this->createTeamWithUser(['manage_meetings']);

        $component = new Calendar;
        $component->teamId = $team->id;
        $component->month = '2026-05';

        $component->nextMonth();

        $this->assertSame('2026-06', $component->month);
    }

    public function test_month_carbon_parses_june_correctly_in_pacific_timezone(): void
    {
        [$user, $team] = $this->createTeamWithUser(['manage_meetings']);
        $team->timezone = 'America/Vancouver';
        $team->save();

        $component = new Calendar;
        $component->teamId = $team->id;
        $component->month = '2026-06';

        $method = new \ReflectionMethod(Calendar::class, 'monthCarbon');
        $method->setAccessible(true);
        $monthStart = $method->invoke($component);

        $this->assertSame('2026-06-01', $monthStart->format('Y-m-d'));
        $this->assertSame(6, $monthStart->month);
    }

    public function test_july_previous_month_navigates_to_june_in_pacific_timezone(): void
    {
        [$user, $team] = $this->createTeamWithUser(['manage_meetings']);
        $team->timezone = 'America/Vancouver';
        $team->save();

        $component = new Calendar;
        $component->teamId = $team->id;
        $component->month = '2026-07';

        $component->previousMonth();

        $this->assertSame('2026-06', $component->month);
    }

    public function test_calendar_route_is_registered_before_meeting_show_route(): void
    {
        [$user, $team] = $this->createTeamWithUser(['manage_meetings']);

        $matched = Route::getRoutes()->match(
            Request::create(entity_path("{$team->id}/meetings/calendar"), 'GET')
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

        app(UpdateMeeting::class)->execute(
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

        $url = CalendarFeedToken::feedUrl($user, $team);

        $response = $this->get($url);

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/calendar; charset=utf-8');
        $this->assertStringContainsString('BEGIN:VCALENDAR', $response->getContent());
    }

    public function test_calendar_feed_authorizes_against_feed_team_not_current_team(): void
    {
        [$manager, $teamA] = $this->createTeamWithUser(['manage_meetings']);

        DB::table('permissions')->insert([
            'name' => 'View Meetings Calendar',
            'slug' => 'view_meetings_calendar',
            'description' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $teamB = Team::query()->create([
            'name' => 'Other Team',
            'user_id' => $manager->id,
        ]);

        $viewer = $this->createAdditionalUser($teamB, ['view_meetings_calendar'], 'viewer@example.com');
        $viewer->update(['current_team_id' => $teamA->id]);

        $url = CalendarFeedToken::feedUrl($viewer, $teamB);

        $this->get($url)->assertOk();
    }

    public function test_calendar_feed_rejects_invalid_token(): void
    {
        [$user, $team] = $this->createTeamWithUser(['manage_meetings']);

        $response = $this->get(route('teams.meetings.calendar.feed', [
            'teamId' => $team->id,
            'token' => 'invalid-token',
        ]));

        $response->assertNotFound();
    }

    public function test_user_without_manage_meetings_cannot_create_calendar_events(): void
    {
        [$manager, $team] = $this->createTeamWithUser(['manage_meetings']);
        $member = $this->createAdditionalUser($team, [], 'viewer@example.com');

        $this->expectException(AuthorizationException::class);

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

        $this->expectException(MeetingsException::class);

        app(CreateCalendarEvent::class)->execute(
            $team,
            $user,
            'Invalid range',
            $startsAt,
            $startsAt->copy(),
        );
    }

    public function test_all_day_event_update_can_shorten_to_single_team_day_with_user_timezone(): void
    {
        [$user, $team] = $this->createTeamWithUser(['manage_meetings']);
        $team->update(['timezone' => 'America/Vancouver']);
        $user->update(['timezone' => 'America/Iqaluit']);
        Auth::login($user);

        $timezone = TeamDateTime::teamTimezone($team);
        $event = app(CreateCalendarEvent::class)->execute(
            $team,
            $user,
            'Board planning',
            Carbon::parse('2026-06-19', $timezone)->startOfDay()->utc(),
            Carbon::parse('2026-06-20', $timezone)->endOfDay()->utc(),
            true,
        );

        $component = new Calendar;
        $component->teamId = $team->id;
        $component->editingEventId = $event->id;
        $component->title = $event->title;
        $component->allDay = true;
        $component->startDate = '2026-06-20';
        $component->endDate = '2026-06-20';

        $component->saveEvent();

        $event->refresh();
        $startsAt = TeamDateTime::toTeamTimezone($team, $event->starts_at);
        $endsAt = TeamDateTime::toTeamTimezone($team, $event->ends_at);

        $this->assertSame('2026-06-20', $startsAt->format('Y-m-d'));
        $this->assertSame('2026-06-20', $endsAt->format('Y-m-d'));
    }

    public function test_calendar_viewer_can_open_event_show_modal_without_edit_permission(): void
    {
        [$manager, $team] = $this->createTeamWithUser(['manage_meetings']);

        DB::table('permissions')->insert([
            'name' => 'View Meetings Calendar',
            'slug' => 'view_meetings_calendar',
            'description' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $viewer = $this->createAdditionalUser($team, ['view_meetings_calendar'], 'viewer@example.com');
        $viewer->update(['current_team_id' => $team->id]);
        Auth::login($viewer);

        $event = CalendarEvent::query()->create([
            'team_id' => $team->id,
            'created_by_user_id' => $manager->id,
            'title' => 'Community BBQ',
            'description' => 'Bring a side dish',
            'location' => 'Park pavilion',
            'starts_at' => now()->addDays(2)->utc(),
            'ends_at' => now()->addDays(2)->addHours(2)->utc(),
            'all_day' => false,
        ]);

        $component = new Calendar;
        $component->teamId = $team->id;

        $component->openShowEvent($event->id);

        $this->assertTrue($component->showEntryModal);
        $this->assertSame('event', $component->viewingEntryKind);
        $this->assertSame('Community BBQ', $component->viewTitle);
        $this->assertSame('Park pavilion', $component->viewLocation);
        $this->assertSame('Bring a side dish', $component->viewDescription);
        $this->assertFalse($component->viewingCanEdit);
    }

    public function test_manager_can_open_event_show_modal_with_edit_permission(): void
    {
        [$user, $team] = $this->createTeamWithUser(['manage_meetings']);
        Auth::login($user);

        $event = CalendarEvent::query()->create([
            'team_id' => $team->id,
            'created_by_user_id' => $user->id,
            'title' => 'Board retreat',
            'starts_at' => now()->addDays(4)->utc(),
            'ends_at' => now()->addDays(4)->addHours(3)->utc(),
            'all_day' => false,
        ]);

        $component = new Calendar;
        $component->teamId = $team->id;

        $component->openShowEvent($event->id);

        $this->assertTrue($component->showEntryModal);
        $this->assertTrue($component->viewingCanEdit);
    }

    public function test_calendar_viewer_can_open_meeting_show_modal_without_edit_permission(): void
    {
        [$manager, $team] = $this->createTeamWithUser(['manage_meetings']);

        DB::table('permissions')->insert([
            ['name' => 'View Meetings Calendar', 'slug' => 'view_meetings_calendar', 'description' => null, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'View Meetings List', 'slug' => 'view_meetings_list', 'description' => null, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $viewer = $this->createAdditionalUser($team, ['view_meetings_calendar', 'view_meetings_list'], 'viewer@example.com');
        $viewer->update(['current_team_id' => $team->id]);
        Auth::login($viewer);

        $meeting = Meeting::query()->create([
            'team_id' => $team->id,
            'created_by_user_id' => $manager->id,
            'title' => 'Annual general meeting',
            'type' => MeetingType::Agm,
            'status' => MeetingStatus::Scheduled,
            'scheduled_at' => now()->addDays(5),
            'location' => 'Community hall',
            'agenda_notes' => 'Election of officers',
        ]);

        $component = new Calendar;
        $component->teamId = $team->id;

        $component->openShowMeeting($meeting->id);

        $this->assertTrue($component->showEntryModal);
        $this->assertSame('meeting', $component->viewingEntryKind);
        $this->assertSame('Annual general meeting', $component->viewTitle);
        $this->assertSame('Community hall', $component->viewLocation);
        $this->assertSame('Election of officers', $component->viewDescription);
        $this->assertFalse($component->viewingCanEdit);
        $this->assertSame(
            route('teams.meetings.show', ['team' => $team->id, 'meeting' => $meeting->id]),
            $component->viewEntryUrl
        );
    }
}
