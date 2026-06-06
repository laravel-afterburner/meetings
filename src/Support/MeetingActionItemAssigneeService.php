<?php

namespace Afterburner\Meetings\Support;

use Afterburner\Meetings\Exceptions\MeetingsException;
use Afterburner\Meetings\Models\Meeting;
use App\Models\User;
use Illuminate\Support\Collection;

class MeetingActionItemAssigneeService
{
    /**
     * @return Collection<int, User>
     */
    public function eligibleUsers(Meeting $meeting): Collection
    {
        $meeting->loadMissing('team');

        $invited = app(MeetingAudienceService::class)->invitedUsers($meeting);
        $resolver = config('afterburner-meetings.council_role_resolver');

        if (is_string($resolver) && class_exists($resolver) && method_exists($resolver, 'slugs')) {
            $councilRoleSlugs = $resolver::slugs();
        } else {
            $councilRoleSlugs = config('afterburner-meetings.council_position_role_slugs', [
                'president',
                'vice_president',
                'secretary',
                'treasurer',
            ]);
        }
        $councilHolders = app(MeetingAudienceService::class)->usersWithAnyRole($meeting->team, $councilRoleSlugs);

        return $invited
            ->merge($councilHolders)
            ->unique('id')
            ->sortBy('name')
            ->values();
    }

    public function assertEligible(Meeting $meeting, ?int $userId): void
    {
        if ($userId === null) {
            return;
        }

        $eligibleIds = $this->eligibleUsers($meeting)->pluck('id');

        if (! $eligibleIds->contains($userId)) {
            throw new MeetingsException('Assignee must be invited to the meeting or hold a council position on this team.');
        }
    }
}
