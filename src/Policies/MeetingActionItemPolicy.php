<?php

namespace Afterburner\Meetings\Policies;

use Afterburner\Meetings\Models\Meeting;
use Afterburner\Meetings\Models\MeetingActionItem;
use Afterburner\Meetings\Support\SubscriptionEntitlementGate;
use App\Support\TeamPermissionGate;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class MeetingActionItemPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user, Meeting $meeting): bool
    {
        return $user->can('view', $meeting);
    }

    public function view(User $user, MeetingActionItem $actionItem): bool
    {
        if (! $this->belongsToActionItemTeam($user, $actionItem)) {
            return false;
        }

        if (! SubscriptionEntitlementGate::allows($actionItem->team)) {
            return false;
        }

        return $user->can('view', $actionItem->meeting) || $actionItem->isAssignedTo($user);
    }

    public function create(User $user, Meeting $meeting): bool
    {
        if (! $this->belongsToMeetingTeam($user, $meeting)) {
            return false;
        }

        if (! SubscriptionEntitlementGate::allows($meeting->team)) {
            return false;
        }

        return TeamPermissionGate::allows($user, $meeting->team_id, 'manage_meetings');
    }

    public function update(User $user, MeetingActionItem $actionItem): bool
    {
        if (! $this->belongsToActionItemTeam($user, $actionItem)) {
            return false;
        }

        if (! SubscriptionEntitlementGate::allows($actionItem->team)) {
            return false;
        }

        if (TeamPermissionGate::allows($user, $actionItem->team_id, 'manage_meetings')) {
            return true;
        }

        return $actionItem->isAssignedTo($user);
    }

    public function delete(User $user, MeetingActionItem $actionItem): bool
    {
        if (! $this->belongsToActionItemTeam($user, $actionItem)) {
            return false;
        }

        if (! SubscriptionEntitlementGate::allows($actionItem->team)) {
            return false;
        }

        return TeamPermissionGate::allows($user, $actionItem->team_id, 'manage_meetings');
    }

    public function complete(User $user, MeetingActionItem $actionItem): bool
    {
        return $this->update($user, $actionItem);
    }

    protected function belongsToMeetingTeam(User $user, Meeting $meeting): bool
    {
        return $user->belongsToTeam($meeting->team)
            && $meeting->team_id === $user->currentTeam?->id;
    }

    protected function belongsToActionItemTeam(User $user, MeetingActionItem $actionItem): bool
    {
        return $user->belongsToTeam($actionItem->team)
            && $actionItem->team_id === $user->currentTeam?->id;
    }
}
