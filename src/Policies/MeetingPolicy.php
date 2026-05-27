<?php

namespace Afterburner\Meetings\Policies;

use Afterburner\Meetings\Models\Meeting;
use Afterburner\Meetings\Support\AttendanceRecorderResolver;
use App\Models\Team;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class MeetingPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return (bool) $user->currentTeam?->id
            && $user->belongsToTeam($user->currentTeam);
    }

    public function view(User $user, Meeting $meeting): bool
    {
        return $this->belongsToMeetingTeam($user, $meeting);
    }

    public function create(User $user, Team $team): bool
    {
        if (! $user->belongsToTeam($team)) {
            return false;
        }

        return $user->hasPermission('manage_meetings', $team->id);
    }

    public function update(User $user, Meeting $meeting): bool
    {
        if (! $this->belongsToMeetingTeam($user, $meeting)) {
            return false;
        }

        return $user->hasPermission('manage_meetings', $meeting->team_id)
            && $meeting->isEditable();
    }

    public function delete(User $user, Meeting $meeting): bool
    {
        if (! $this->belongsToMeetingTeam($user, $meeting)) {
            return false;
        }

        return $user->hasPermission('manage_meetings', $meeting->team_id);
    }

    public function manageAttendance(User $user, Meeting $meeting): bool
    {
        if (! $this->belongsToMeetingTeam($user, $meeting)) {
            return false;
        }

        return app(AttendanceRecorderResolver::class)->canRecord($user, $meeting);
    }

    public function recordMinutes(User $user, Meeting $meeting): bool
    {
        return $this->manageAttendance($user, $meeting);
    }

    public function linkBallots(User $user, Meeting $meeting): bool
    {
        if (! $this->belongsToMeetingTeam($user, $meeting)) {
            return false;
        }

        return $user->hasPermission('manage_meetings', $meeting->team_id);
    }

    public function attachDocuments(User $user, Meeting $meeting): bool
    {
        if (! $this->belongsToMeetingTeam($user, $meeting)) {
            return false;
        }

        return $user->hasPermission('manage_meetings', $meeting->team_id)
            && $meeting->isEditable();
    }

    protected function belongsToMeetingTeam(User $user, Meeting $meeting): bool
    {
        return $user->belongsToTeam($meeting->team)
            && $meeting->team_id === $user->currentTeam?->id;
    }
}
