<?php

namespace Afterburner\Meetings\Listeners;

use Afterburner\Meetings\Events\MeetingScheduled;
use Afterburner\Meetings\Notifications\MeetingScheduledNotification;
use Afterburner\Meetings\Support\MeetingAudienceService;

class NotifyMeetingAudience
{
    public function __construct(
        protected MeetingAudienceService $audienceService,
    ) {}

    public function handle(MeetingScheduled $event): void
    {
        $meeting = $event->meeting->fresh(['team']);

        if ($meeting->invitations_sent_at !== null) {
            return;
        }

        $users = $this->audienceService
            ->invitedUsers($meeting)
            ->filter(fn ($user) => $user->email_verified_at !== null)
            ->filter(fn ($user) => $user->id !== $meeting->created_by_user_id);

        if ($users->isEmpty()) {
            return;
        }

        foreach ($users as $user) {
            $user->notify(new MeetingScheduledNotification($meeting));
        }

        $meeting->forceFill(['invitations_sent_at' => now()])->save();
    }
}
