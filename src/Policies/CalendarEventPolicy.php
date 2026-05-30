<?php

namespace Afterburner\Meetings\Policies;

use Afterburner\Meetings\Models\CalendarEvent;
use Afterburner\Meetings\Support\SubscriptionEntitlementGate;
use Afterburner\Meetings\Support\TeamPermissionGate;
use App\Models\Team;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class CalendarEventPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        if (! $user->currentTeam?->id || ! $user->belongsToTeam($user->currentTeam)) {
            return false;
        }

        return SubscriptionEntitlementGate::allows($user->currentTeam);
    }

    public function view(User $user, CalendarEvent $event): bool
    {
        if (! $this->belongsToEventTeam($user, $event)) {
            return false;
        }

        return SubscriptionEntitlementGate::allows($event->team);
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

    public function update(User $user, CalendarEvent $event): bool
    {
        if (! $this->belongsToEventTeam($user, $event)) {
            return false;
        }

        if (! SubscriptionEntitlementGate::allows($event->team)) {
            return false;
        }

        return TeamPermissionGate::allows($user, $event->team_id, 'manage_meetings');
    }

    public function delete(User $user, CalendarEvent $event): bool
    {
        return $this->update($user, $event);
    }

    protected function belongsToEventTeam(User $user, CalendarEvent $event): bool
    {
        return $user->belongsToTeam($event->team)
            && $event->team_id === $user->currentTeam?->id;
    }
}
