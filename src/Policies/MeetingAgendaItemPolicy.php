<?php

namespace Afterburner\Meetings\Policies;

use Afterburner\Meetings\Models\Meeting;
use Afterburner\Meetings\Models\MeetingAgendaItem;
use Afterburner\Meetings\Support\SubscriptionEntitlementGate;
use App\Support\TeamPermissionGate;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class MeetingAgendaItemPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user, Meeting $meeting): bool
    {
        return $user->can('view', $meeting);
    }

    public function view(User $user, MeetingAgendaItem $agendaItem): bool
    {
        if (! $this->belongsToAgendaItemTeam($user, $agendaItem)) {
            return false;
        }

        if (! SubscriptionEntitlementGate::allows($agendaItem->team)) {
            return false;
        }

        return $user->can('view', $agendaItem->meeting);
    }

    public function create(User $user, Meeting $meeting): bool
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

    public function update(User $user, MeetingAgendaItem $agendaItem): bool
    {
        if (! $this->belongsToAgendaItemTeam($user, $agendaItem)) {
            return false;
        }

        if (! SubscriptionEntitlementGate::allows($agendaItem->team)) {
            return false;
        }

        return TeamPermissionGate::allows($user, $agendaItem->team_id, 'manage_meetings')
            && $agendaItem->meeting->isEditable();
    }

    public function delete(User $user, MeetingAgendaItem $agendaItem): bool
    {
        return $this->update($user, $agendaItem);
    }

    protected function belongsToMeetingTeam(User $user, Meeting $meeting): bool
    {
        return $user->belongsToTeam($meeting->team)
            && $meeting->team_id === $user->currentTeam?->id;
    }

    protected function belongsToAgendaItemTeam(User $user, MeetingAgendaItem $agendaItem): bool
    {
        return $user->belongsToTeam($agendaItem->team)
            && $agendaItem->team_id === $user->currentTeam?->id;
    }
}
