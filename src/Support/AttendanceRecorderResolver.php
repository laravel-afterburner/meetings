<?php

namespace Afterburner\Meetings\Support;

use Afterburner\Meetings\Enums\AttendanceStatus;
use Afterburner\Meetings\Models\Meeting;
use App\Models\User;

class AttendanceRecorderResolver
{
    public function __construct(
        protected MeetingAudienceService $audienceService,
    ) {}

    public function recorderFor(Meeting $meeting): ?User
    {
        $meeting->loadMissing(['team', 'creator', 'attendances']);
        $chain = config('afterburner-meetings.attendance_recorder_chain', []);
        $presentUserIds = $meeting->attendances
            ->where('status', AttendanceStatus::Present)
            ->pluck('user_id');

        foreach ($chain as $step) {
            if ($step === 'organizer') {
                if ($presentUserIds->contains($meeting->created_by_user_id)) {
                    return $meeting->creator;
                }

                continue;
            }

            $candidates = $this->audienceService
                ->usersWithRole($meeting->team, $step)
                ->whereIn('id', $presentUserIds);

            if ($candidates->isNotEmpty()) {
                return $candidates->first();
            }
        }

        foreach ($chain as $step) {
            if ($step === 'organizer') {
                return $meeting->creator;
            }

            $candidate = $this->audienceService->usersWithRole($meeting->team, $step)->first();

            if ($candidate) {
                return $candidate;
            }
        }

        return $meeting->creator;
    }

    public function canRecord(User $user, Meeting $meeting): bool
    {
        $recorder = $this->recorderFor($meeting);

        return $recorder !== null && $recorder->id === $user->id;
    }
}
