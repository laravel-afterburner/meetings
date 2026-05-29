<?php

namespace Afterburner\Meetings\Support;

use Afterburner\Meetings\Contracts\MeetingMinutesAttendanceSummaryProvider;
use Afterburner\Meetings\Enums\AttendanceStatus;
use Afterburner\Meetings\Models\Meeting;

class DefaultMeetingMinutesAttendanceSummaryProvider implements MeetingMinutesAttendanceSummaryProvider
{
    public function summaryLines(Meeting $meeting): array
    {
        $meeting->loadMissing('attendances');

        $invited = $meeting->invitedUsers()->count();
        $present = $meeting->attendances->where('status', AttendanceStatus::Present)->count();
        $absent = $meeting->attendances->where('status', AttendanceStatus::EligibleNotPresent)->count();
        $recorded = $meeting->attendances->count();

        if ($invited === 0 && $recorded === 0) {
            return ['No attendance recorded.'];
        }

        return [
            sprintf('%d of %d invited members present.', $present, $invited),
            sprintf('%d marked absent.', $absent),
            sprintf('%d attendance records recorded.', $recorded),
        ];
    }
}
