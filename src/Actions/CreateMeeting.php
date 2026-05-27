<?php

namespace Afterburner\Meetings\Actions;

use Afterburner\Meetings\Enums\MeetingStatus;
use Afterburner\Meetings\Enums\MeetingType;
use Afterburner\Meetings\Exceptions\MeetingsException;
use Afterburner\Meetings\Models\Meeting;
use Afterburner\Meetings\Support\MeetingAudienceService;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class CreateMeeting
{
    public function __construct(
        protected MeetingAudienceService $audienceService,
    ) {}

    public function execute(
        Team $team,
        User $user,
        string $title,
        MeetingType $type,
        ?string $location = null,
        ?string $virtualLink = null,
        ?string $agendaNotes = null,
        ?\DateTimeInterface $scheduledAt = null,
        ?array $targetRoleSlugs = null,
    ): Meeting {
        Gate::forUser($user)->authorize('create', [Meeting::class, $team]);

        if ($team->id !== $user->currentTeam?->id && ! $user->belongsToTeam($team)) {
            throw new MeetingsException('You do not belong to this team.');
        }

        $roles = $targetRoleSlugs ?? $this->audienceService->defaultRolesForType($type->value);

        return Meeting::query()->create([
            'team_id' => $team->id,
            'created_by_user_id' => $user->id,
            'title' => $title,
            'type' => $type,
            'status' => MeetingStatus::Draft,
            'location' => $location,
            'virtual_link' => $virtualLink,
            'agenda_notes' => $agendaNotes,
            'scheduled_at' => $scheduledAt,
            'target_role_slugs' => $roles,
        ]);
    }
}
