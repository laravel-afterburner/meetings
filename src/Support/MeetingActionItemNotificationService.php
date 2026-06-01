<?php

namespace Afterburner\Meetings\Support;

use Afterburner\Meetings\Enums\MeetingStatus;
use Afterburner\Meetings\Events\MeetingActionItemAssigned;
use Afterburner\Meetings\Models\Meeting;
use Afterburner\Meetings\Models\MeetingActionItem;
use Afterburner\Meetings\Notifications\MeetingActionItemAssignedNotification;
use Afterburner\Meetings\Notifications\MeetingActionItemReassignedNotification;
use Illuminate\Support\Facades\DB;

class MeetingActionItemNotificationService
{
    public function shouldNotifyOnAssign(Meeting $meeting): bool
    {
        return $meeting->status === MeetingStatus::Completed;
    }

    public function notifyAssignee(MeetingActionItem $actionItem, bool $reassigned = false): void
    {
        $actionItem->loadMissing(['meeting', 'assignee']);

        if ($actionItem->assigned_to_user_id === null || $actionItem->assignee === null) {
            return;
        }

        if (! $this->shouldNotifyOnAssign($actionItem->meeting)) {
            return;
        }

        $notification = $reassigned
            ? new MeetingActionItemReassignedNotification($actionItem)
            : new MeetingActionItemAssignedNotification($actionItem);

        $actionItem->assignee->notify($notification);

        $databaseId = $actionItem->assignee->notifications()
            ->latest()
            ->value('id');

        $actionItem->forceFill([
            'assignee_notified_at' => now(),
            'assignee_notification_id' => $databaseId,
        ])->save();

        if (! $reassigned) {
            MeetingActionItemAssigned::dispatch($actionItem->fresh(['meeting', 'assignee']));
        }
    }

    public function notifyAllPendingForMeeting(Meeting $meeting): void
    {
        $meeting->actionItems()
            ->with(['assignee', 'meeting'])
            ->whereNotNull('assigned_to_user_id')
            ->whereNull('assignee_notified_at')
            ->get()
            ->each(fn (MeetingActionItem $actionItem) => $this->notifyAssignee($actionItem));
    }

    public function syncAssigneeChange(
        MeetingActionItem $actionItem,
        ?int $previousAssigneeId,
        ?int $newAssigneeId,
    ): void {
        if ($previousAssigneeId === $newAssigneeId) {
            return;
        }

        $previousNotificationWasRead = false;

        if ($previousAssigneeId !== null && $actionItem->assignee_notification_id !== null) {
            $previousNotificationWasRead = $this->revokeNotificationForPreviousAssignee(
                $actionItem,
                $previousAssigneeId,
            );
        }

        $actionItem->forceFill([
            'assignee_notified_at' => null,
            'assignee_notification_id' => null,
        ])->save();

        if ($newAssigneeId !== null) {
            $this->notifyAssignee(
                $actionItem->fresh(['meeting', 'assignee']),
                reassigned: $previousAssigneeId !== null && $previousNotificationWasRead,
            );
        }
    }

    protected function revokeNotificationForPreviousAssignee(MeetingActionItem $actionItem, int $assigneeId): bool
    {
        $notification = DB::table('notifications')
            ->where('id', $actionItem->assignee_notification_id)
            ->where('notifiable_id', $assigneeId)
            ->first();

        if ($notification === null) {
            return false;
        }

        if ($notification->read_at === null) {
            DB::table('notifications')->where('id', $notification->id)->delete();

            return false;
        }

        return true;
    }
}
