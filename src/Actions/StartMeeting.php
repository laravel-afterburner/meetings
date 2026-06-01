<?php

namespace Afterburner\Meetings\Actions;

use Afterburner\Meetings\Enums\AttendanceStatus;
use Afterburner\Meetings\Enums\MeetingStatus;
use Afterburner\Meetings\Exceptions\MeetingsException;
use Afterburner\Meetings\Models\Meeting;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class StartMeeting
{
    /**
     * @param  array<int, string>  $attendanceByUserId
     */
    public function execute(Meeting $meeting, User $user, array $attendanceByUserId): Meeting
    {
        Gate::forUser($user)->authorize('start', $meeting);

        if ($meeting->status !== MeetingStatus::Scheduled) {
            throw new MeetingsException('Only scheduled meetings can be started.');
        }

        foreach ($attendanceByUserId as $attendeeUserId => $status) {
            if (! in_array($status, ['present', 'eligible_not_present'], true)) {
                continue;
            }

            app(RecordAttendance::class)->execute(
                $meeting,
                $user,
                (int) $attendeeUserId,
                AttendanceStatus::from($status),
            );
        }

        return app(UpdateMeeting::class)->execute(
            $meeting,
            $user,
            $meeting->title,
            $meeting->type,
            MeetingStatus::InProgress,
            $meeting->location,
            $meeting->virtual_link,
            $meeting->agenda_notes,
            $meeting->scheduled_at,
            $meeting->target_role_slugs,
        );
    }
}
