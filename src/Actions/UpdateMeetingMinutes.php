<?php

namespace Afterburner\Meetings\Actions;

use Afterburner\Meetings\Exceptions\MeetingsException;
use Afterburner\Meetings\Models\Meeting;
use Afterburner\Meetings\Support\MeetingsAuditLogger;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class UpdateMeetingMinutes
{
    public function execute(
        Meeting $meeting,
        User $user,
        ?string $minutes,
        bool $finalize = false,
    ): Meeting {
        Gate::forUser($user)->authorize('recordMinutes', $meeting);

        if (! $meeting->minutesAreEditable() && ! $finalize) {
            throw new MeetingsException('Meeting minutes can no longer be edited.');
        }

        $payload = [
            'minutes' => $minutes,
        ];

        if ($finalize) {
            $payload['minutes_finalized_at'] = now();
            $payload['minutes_finalized_by_user_id'] = $user->id;
        }

        $meeting->update($payload);

        $meeting = $meeting->fresh();

        MeetingsAuditLogger::minutesUpdated($meeting, $user, $finalize);

        return $meeting;
    }
}
