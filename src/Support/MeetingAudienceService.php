<?php

namespace Afterburner\Meetings\Support;

use Afterburner\Meetings\Models\Meeting;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Collection;

class MeetingAudienceService
{
    /**
     * @return Collection<int, User>
     */
    public function invitedUsers(Meeting $meeting): Collection
    {
        $meeting->loadMissing('team');
        $roleSlugs = $meeting->target_role_slugs ?? [];

        if ($roleSlugs === []) {
            return collect();
        }

        return $this->usersWithAnyRole($meeting->team, $roleSlugs);
    }

    /**
     * @param  array<int, string>  $roleSlugs
     * @return Collection<int, User>
     */
    public function usersWithAnyRole(Team $team, array $roleSlugs): Collection
    {
        if ($roleSlugs === []) {
            return collect();
        }

        $users = $team->users()->get();

        if ($team->owner) {
            $users = $users->push($team->owner);
        }

        return $users
            ->unique('id')
            ->filter(fn (User $user) => $this->userHasAnyRole($user, $team->id, $roleSlugs))
            ->sortBy('name')
            ->values();
    }

    /**
     * @param  array<int, string>  $roleSlugs
     * @return Collection<int, User>
     */
    public function usersWithRole(Team $team, string $roleSlug): Collection
    {
        return $this->usersWithAnyRole($team, [$roleSlug]);
    }

    /**
     * @return Collection<int, Role>
     */
    public function selectableRoles(): Collection
    {
        $slugs = config('afterburner-meetings.selectable_audience_role_slugs');

        $query = Role::query()->orderBy('hierarchy');

        if (is_array($slugs) && $slugs !== []) {
            $query->whereIn('slug', $slugs);
        }

        return $query->get();
    }

    /**
     * @return array<int, string>
     */
    public function defaultRolesForType(string $meetingType): array
    {
        return config("afterburner-meetings.default_target_roles_by_type.{$meetingType}", []);
    }

    /**
     * @param  array<int, string>  $roleSlugs
     */
    protected function userHasAnyRole(User $user, int $teamId, array $roleSlugs): bool
    {
        $userRoleSlugs = $user->roles()
            ->wherePivot('team_id', $teamId)
            ->pluck('slug')
            ->all();

        return array_intersect($roleSlugs, $userRoleSlugs) !== [];
    }
}
