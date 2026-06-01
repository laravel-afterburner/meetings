<?php

namespace Afterburner\Meetings\Http\Controllers;

use Afterburner\Meetings\Models\Meeting;
use App\Models\Team;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class MeetingsController
{
    public function index(Team $team): View
    {
        $this->ensureTeamAccess($team);
        abort_unless(Auth::user()->can('viewAny', Meeting::class), 403);

        return view('afterburner-meetings::meetings.index', [
            'team' => $team,
        ]);
    }

    public function create(Team $team): View
    {
        $this->ensureTeamAccess($team);

        return view('afterburner-meetings::meetings.create', [
            'team' => $team,
        ]);
    }

    public function show(Team $team, Meeting $meeting): View
    {
        $this->ensureTeamAccess($team);
        $this->ensureMeetingBelongsToTeam($team, $meeting);

        return view('afterburner-meetings::meetings.show', [
            'team' => $team,
            'meeting' => $meeting,
        ]);
    }

    public function inProgress(Team $team, Meeting $meeting): View
    {
        $this->ensureTeamAccess($team);
        $this->ensureMeetingBelongsToTeam($team, $meeting);

        return view('afterburner-meetings::meetings.in-progress', [
            'team' => $team,
            'meeting' => $meeting,
        ]);
    }

    public function completed(Team $team, Meeting $meeting): View
    {
        $this->ensureTeamAccess($team);
        $this->ensureMeetingBelongsToTeam($team, $meeting);

        return view('afterburner-meetings::meetings.completed', [
            'team' => $team,
            'meeting' => $meeting,
        ]);
    }

    public function edit(Team $team, Meeting $meeting): View
    {
        $this->ensureTeamAccess($team);
        $this->ensureMeetingBelongsToTeam($team, $meeting);

        return view('afterburner-meetings::meetings.create', [
            'team' => $team,
            'meeting' => $meeting,
        ]);
    }

    protected function ensureTeamAccess(Team $team): void
    {
        if (! Auth::user()->belongsToTeam($team)) {
            abort(403, 'Access denied.');
        }
    }

    protected function ensureMeetingBelongsToTeam(Team $team, Meeting $meeting): void
    {
        if ($meeting->team_id !== $team->id) {
            abort(404);
        }
    }
}
