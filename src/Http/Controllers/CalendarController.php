<?php

namespace Afterburner\Meetings\Http\Controllers;

use Afterburner\Meetings\Models\CalendarEvent;
use Afterburner\Meetings\Support\CalendarFeedAccess;
use Afterburner\Meetings\Support\CalendarFeedToken;
use Afterburner\Meetings\Support\CalendarQuery;
use Afterburner\Meetings\Support\IcsCalendarExporter;
use Afterburner\Meetings\Support\TeamDateTime;
use App\Models\Team;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class CalendarController
{
    public function index(Team $team): View
    {
        $this->ensureTeamAccess($team);
        abort_unless(config('afterburner-meetings.calendar.enabled', true), 404);
        abort_unless(Auth::user()->can('viewAny', CalendarEvent::class), 403);

        return view('afterburner-meetings::meetings.calendar', [
            'team' => $team,
        ]);
    }

    public function feed(int $teamId, Request $request): Response
    {
        $team = Team::query()->findOrFail($teamId);

        $token = $request->query('token');

        if (! is_string($token) || $token === '') {
            abort(404);
        }

        $payload = CalendarFeedToken::resolve($token);

        if (! $payload || $payload['team_id'] !== $team->id) {
            abort(404);
        }

        $user = User::query()->find($payload['user_id']);

        if (! $user || ! $user->belongsToTeam($team)) {
            abort(404);
        }

        abort_unless(CalendarFeedAccess::allows($user, $team), 403);

        $timezone = TeamDateTime::teamTimezone($team);
        $rangeStart = Carbon::now($timezone)->subMonths(6)->startOfMonth();
        $rangeEnd = Carbon::now($timezone)->addMonths(18)->endOfMonth();

        $entries = app(CalendarQuery::class)->entriesForRange($team, $rangeStart, $rangeEnd);
        $ics = app(IcsCalendarExporter::class)->export(
            $team,
            $entries,
            $team->name.' Calendar'
        );

        return response($ics, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'inline; filename="'.$team->id.'-calendar.ics"',
        ]);
    }

    protected function ensureTeamAccess(Team $team): void
    {
        if (! Auth::user()->belongsToTeam($team)) {
            abort(403, 'Access denied.');
        }
    }
}
