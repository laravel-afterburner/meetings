<?php

namespace Afterburner\Meetings\Actions;

use Afterburner\Meetings\Enums\MeetingStatus;
use Afterburner\Meetings\Exceptions\MeetingsException;
use Afterburner\Meetings\Models\Meeting;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class DeleteMeeting
{
    public function execute(Meeting $meeting, User $user): void
    {
        Gate::forUser($user)->authorize('delete', $meeting);

        if ($meeting->status !== MeetingStatus::Draft) {
            throw new MeetingsException('Only draft meetings can be deleted.');
        }

        $meeting->delete();
    }
}
