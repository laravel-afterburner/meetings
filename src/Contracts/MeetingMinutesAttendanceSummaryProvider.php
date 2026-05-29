<?php

namespace Afterburner\Meetings\Contracts;

use Afterburner\Meetings\Models\Meeting;

interface MeetingMinutesAttendanceSummaryProvider
{
    /**
     * @return array<int, string>
     */
    public function summaryLines(Meeting $meeting): array;
}
