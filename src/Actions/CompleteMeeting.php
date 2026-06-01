<?php

namespace Afterburner\Meetings\Actions;

use Afterburner\Meetings\Enums\MeetingStatus;
use Afterburner\Meetings\Exceptions\MeetingsException;
use Afterburner\Meetings\Models\Meeting;
use Afterburner\Meetings\Support\MeetingActionItemNotificationService;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class CompleteMeeting
{
    public function execute(Meeting $meeting, User $user): Meeting
    {
        Gate::forUser($user)->authorize('complete', $meeting);

        if ($meeting->status !== MeetingStatus::InProgress) {
            throw new MeetingsException('Only meetings in progress can be finished.');
        }

        $meeting = app(UpdateMeeting::class)->execute(
            $meeting,
            $user,
            $meeting->title,
            $meeting->type,
            MeetingStatus::Completed,
            $meeting->location,
            $meeting->virtual_link,
            $meeting->agenda_notes,
            $meeting->scheduled_at,
            $meeting->target_role_slugs,
        );

        app(MeetingActionItemNotificationService::class)->notifyAllPendingForMeeting($meeting);

        return $meeting;
    }
}
