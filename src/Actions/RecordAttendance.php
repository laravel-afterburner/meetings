<?php

namespace Afterburner\Meetings\Actions;

use Afterburner\Meetings\Enums\AttendanceStatus;
use Afterburner\Meetings\Exceptions\MeetingsException;
use Afterburner\Meetings\Models\Meeting;
use Afterburner\Meetings\Models\MeetingAttendance;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class RecordAttendance
{
    public function execute(
        Meeting $meeting,
        User $recorder,
        int $attendeeUserId,
        AttendanceStatus $status,
        ?string $notes = null,
    ): MeetingAttendance {
        Gate::forUser($recorder)->authorize('manageAttendance', $meeting);

        if ($meeting->team_id !== $recorder->currentTeam?->id) {
            throw new MeetingsException('You do not belong to this team.');
        }

        $invitedUserIds = $meeting->invitedUsers()->pluck('id');

        if (! $invitedUserIds->contains($attendeeUserId)) {
            throw new MeetingsException('This person was not invited to the meeting.');
        }

        return MeetingAttendance::query()->updateOrCreate(
            [
                'meeting_id' => $meeting->id,
                'user_id' => $attendeeUserId,
            ],
            [
                'status' => $status,
                'recorded_by_user_id' => $recorder->id,
                'notes' => $notes,
            ]
        );
    }
}
