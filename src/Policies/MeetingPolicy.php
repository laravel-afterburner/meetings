<?php

namespace Afterburner\Meetings\Policies;

use Afterburner\Meetings\Models\Meeting;
use Afterburner\Meetings\Support\AttendanceRecorderResolver;
use Afterburner\Meetings\Support\SubscriptionEntitlementGate;
use Afterburner\Meetings\Support\TeamPermissionGate;
use App\Models\Team;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class MeetingPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        if (! $user->currentTeam?->id || ! $user->belongsToTeam($user->currentTeam)) {
            return false;
        }

        return SubscriptionEntitlementGate::allows($user->currentTeam);
    }

    public function view(User $user, Meeting $meeting): bool
    {
        if (! $this->belongsToMeetingTeam($user, $meeting)) {
            return false;
        }

        return SubscriptionEntitlementGate::allows($meeting->team);
    }

    public function create(User $user, Team $team): bool
    {
        if (! $user->belongsToTeam($team)) {
            return false;
        }

        if (! SubscriptionEntitlementGate::allows($team)) {
            return false;
        }

        return TeamPermissionGate::allows($user, $team->id, 'manage_meetings');
    }

    public function update(User $user, Meeting $meeting): bool
    {
        if (! $this->belongsToMeetingTeam($user, $meeting)) {
            return false;
        }

        if (! SubscriptionEntitlementGate::allows($meeting->team)) {
            return false;
        }

        return TeamPermissionGate::allows($user, $meeting->team_id, 'manage_meetings')
            && $meeting->isEditable();
    }

    public function delete(User $user, Meeting $meeting): bool
    {
        if (! $this->belongsToMeetingTeam($user, $meeting)) {
            return false;
        }

        if (! SubscriptionEntitlementGate::allows($meeting->team)) {
            return false;
        }

        return TeamPermissionGate::allows($user, $meeting->team_id, 'manage_meetings');
    }

    public function manageAttendance(User $user, Meeting $meeting): bool
    {
        if (! $this->belongsToMeetingTeam($user, $meeting)) {
            return false;
        }

        if (! SubscriptionEntitlementGate::allows($meeting->team)) {
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

        if (! SubscriptionEntitlementGate::allows($meeting->team)) {
            return false;
        }

        return TeamPermissionGate::allows($user, $meeting->team_id, 'manage_meetings');
    }

    public function attachDocuments(User $user, Meeting $meeting): bool
    {
        if (! $this->belongsToMeetingTeam($user, $meeting)) {
            return false;
        }

        if (! SubscriptionEntitlementGate::allows($meeting->team)) {
            return false;
        }

        return TeamPermissionGate::allows($user, $meeting->team_id, 'manage_meetings')
            && $meeting->isEditable();
    }

    protected function belongsToMeetingTeam(User $user, Meeting $meeting): bool
    {
        return $user->belongsToTeam($meeting->team)
            && $meeting->team_id === $user->currentTeam?->id;
    }
}
