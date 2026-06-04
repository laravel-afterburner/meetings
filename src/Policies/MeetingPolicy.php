<?php

namespace Afterburner\Meetings\Policies;

use Afterburner\Meetings\Enums\MeetingStatus;
use Afterburner\Meetings\Models\Meeting;
use Afterburner\Meetings\Support\AttendanceRecorderResolver;
use Afterburner\Meetings\Support\DocumentsIntegration;
use Afterburner\Meetings\Support\MeetingsPermissions;
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

        if (! SubscriptionEntitlementGate::allows($user->currentTeam)) {
            return false;
        }

        return MeetingsPermissions::canAccessModule($user, $user->currentTeam);
    }

    public function view(User $user, Meeting $meeting): bool
    {
        if (! $this->belongsToMeetingTeam($user, $meeting)) {
            return false;
        }

        if (! SubscriptionEntitlementGate::allows($meeting->team)) {
            return false;
        }

        return MeetingsPermissions::canViewSection($user, $meeting->team, MeetingsPermissions::SECTION_MEETINGS)
            || TeamPermissionGate::allowsAny($user, $meeting->team_id, [
                'create_meetings',
                'manage_meetings',
            ]);
    }

    public function create(User $user, Team $team): bool
    {
        if (! $user->belongsToTeam($team)) {
            return false;
        }

        if (! SubscriptionEntitlementGate::allows($team)) {
            return false;
        }

        return TeamPermissionGate::allows($user, $team->id, 'create_meetings');
    }

    public function update(User $user, Meeting $meeting): bool
    {
        if (! $this->belongsToMeetingTeam($user, $meeting)) {
            return false;
        }

        if (! SubscriptionEntitlementGate::allows($meeting->team)) {
            return false;
        }

        return TeamPermissionGate::allows($user, $meeting->team_id, 'edit_meetings')
            && ($meeting->isEditable() || $meeting->status === MeetingStatus::Completed);
    }

    public function delete(User $user, Meeting $meeting): bool
    {
        if (! $this->belongsToMeetingTeam($user, $meeting)) {
            return false;
        }

        if (! SubscriptionEntitlementGate::allows($meeting->team)) {
            return false;
        }

        return TeamPermissionGate::allows($user, $meeting->team_id, 'delete_meetings');
    }

    public function manageAttendance(User $user, Meeting $meeting): bool
    {
        if (! $this->belongsToMeetingTeam($user, $meeting)) {
            return false;
        }

        if (! SubscriptionEntitlementGate::allows($meeting->team)) {
            return false;
        }

        if (TeamPermissionGate::allows($user, $meeting->team_id, 'conduct_meetings')) {
            return true;
        }

        return app(AttendanceRecorderResolver::class)->canRecord($user, $meeting);
    }

    public function recordMinutes(User $user, Meeting $meeting): bool
    {
        if (! $this->belongsToMeetingTeam($user, $meeting)) {
            return false;
        }

        if (! SubscriptionEntitlementGate::allows($meeting->team)) {
            return false;
        }

        if ($meeting->status === MeetingStatus::Completed
            && TeamPermissionGate::allows($user, $meeting->team_id, 'save_meeting_minutes')) {
            return true;
        }

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

        return TeamPermissionGate::allows($user, $meeting->team_id, 'edit_meetings');
    }

    public function attachDocuments(User $user, Meeting $meeting): bool
    {
        if (! $this->belongsToMeetingTeam($user, $meeting)) {
            return false;
        }

        if (! SubscriptionEntitlementGate::allows($meeting->team)) {
            return false;
        }

        return TeamPermissionGate::allows($user, $meeting->team_id, 'edit_meetings')
            && $meeting->isEditable();
    }

    public function start(User $user, Meeting $meeting): bool
    {
        if (! $this->belongsToMeetingTeam($user, $meeting)) {
            return false;
        }

        if (! SubscriptionEntitlementGate::allows($meeting->team)) {
            return false;
        }

        return TeamPermissionGate::allows($user, $meeting->team_id, 'conduct_meetings')
            && $meeting->status === MeetingStatus::Scheduled;
    }

    public function conductSession(User $user, Meeting $meeting): bool
    {
        if (! $this->belongsToMeetingTeam($user, $meeting)) {
            return false;
        }

        if (! SubscriptionEntitlementGate::allows($meeting->team)) {
            return false;
        }

        return $meeting->status === MeetingStatus::InProgress
            && $this->manageAttendance($user, $meeting);
    }

    public function complete(User $user, Meeting $meeting): bool
    {
        if (! $this->belongsToMeetingTeam($user, $meeting)) {
            return false;
        }

        if (! SubscriptionEntitlementGate::allows($meeting->team)) {
            return false;
        }

        return TeamPermissionGate::allows($user, $meeting->team_id, 'conduct_meetings')
            && $meeting->status === MeetingStatus::InProgress;
    }

    public function reviseAfterCompletion(User $user, Meeting $meeting): bool
    {
        if (! $this->belongsToMeetingTeam($user, $meeting)) {
            return false;
        }

        if (! SubscriptionEntitlementGate::allows($meeting->team)) {
            return false;
        }

        return TeamPermissionGate::allows($user, $meeting->team_id, 'edit_meetings')
            && $meeting->status === MeetingStatus::Completed;
    }

    public function compilePackage(User $user, Meeting $meeting): bool
    {
        if (! $this->reviseAfterCompletion($user, $meeting)) {
            return false;
        }

        return DocumentsIntegration::isEnabled();
    }

    protected function belongsToMeetingTeam(User $user, Meeting $meeting): bool
    {
        return $user->belongsToTeam($meeting->team)
            && $meeting->team_id === $user->currentTeam?->id;
    }
}
