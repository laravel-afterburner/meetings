<?php

namespace Afterburner\Meetings\Actions;

use Afterburner\Meetings\Exceptions\MeetingsException;
use Afterburner\Meetings\Models\Meeting;
use Afterburner\Meetings\Models\MeetingAttendance;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class RemoveAttendance
{
    public function execute(
        Meeting $meeting,
        User $user,
        int $attendeeUserId,
    ): void {
        Gate::forUser($user)->authorize('manageAttendance', $meeting);

        $attendance = MeetingAttendance::query()
            ->where('meeting_id', $meeting->id)
            ->where('user_id', $attendeeUserId)
            ->first();

        if (! $attendance) {
            throw new MeetingsException('Attendance record not found.');
        }

        $attendance->delete();
    }
}
