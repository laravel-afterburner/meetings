<?php

namespace Afterburner\Meetings\Actions;

use Afterburner\Meetings\Models\Meeting;
use Afterburner\Meetings\Support\MeetingMinutesSectionBuilder;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class BuildMeetingMinutesDraft
{
    public function __construct(
        protected MeetingMinutesSectionBuilder $sectionBuilder,
    ) {}

    public function execute(Meeting $meeting, ?User $user = null): string
    {
        if ($user !== null) {
            Gate::forUser($user)->authorize('recordMinutes', $meeting);
        }

        return $this->sectionBuilder->buildAll($meeting);
    }

    public function section(Meeting $meeting, string $sectionKey, ?User $user = null): ?string
    {
        if ($user !== null) {
            Gate::forUser($user)->authorize('recordMinutes', $meeting);
        }

        return $this->sectionBuilder->build($meeting, $sectionKey);
    }
}
