<?php

namespace Afterburner\Meetings\Listeners;

use Afterburner\Meetings\Events\MeetingActionItemAssigned;

/**
 * Stub listener for action-item assignment notifications.
 *
 * The meetings package does not send email or in-app notifications. Host apps
 * should subscribe to MeetingActionItemAssigned (or extend this listener) to
 * notify assignees when an action item is assigned or reassigned.
 */
class NotifyMeetingActionItemAssignee
{
    public function handle(MeetingActionItemAssigned $event): void
    {
        // Intentionally empty — implement notification delivery in the host app.
    }
}
