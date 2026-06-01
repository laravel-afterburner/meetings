<?php

namespace Afterburner\Meetings\Notifications;

use Afterburner\Meetings\Models\MeetingActionItem;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class MeetingActionItemReassignedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public MeetingActionItem $actionItem) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $this->actionItem->loadMissing('meeting');

        return [
            'type' => 'meeting_action_item_reassigned',
            'meeting_id' => $this->actionItem->meeting_id,
            'meeting_title' => $this->actionItem->meeting->title,
            'action_item_id' => $this->actionItem->id,
            'action_item_title' => $this->actionItem->title,
            'message' => __('An action item from :meeting has been reassigned to you.', [
                'meeting' => $this->actionItem->meeting->title,
            ]),
        ];
    }
}
