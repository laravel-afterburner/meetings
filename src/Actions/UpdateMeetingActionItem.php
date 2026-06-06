<?php

namespace Afterburner\Meetings\Actions;

use Afterburner\Meetings\Enums\ActionItemStatus;
use Afterburner\Meetings\Exceptions\MeetingsException;
use Afterburner\Meetings\Models\MeetingActionItem;
use Afterburner\Meetings\Support\MeetingActionItemAssigneeService;
use Afterburner\Meetings\Support\MeetingActionItemNotificationService;
use Afterburner\Meetings\Support\MeetingsAuditLogger;
use App\Support\TeamPermissionGate;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class UpdateMeetingActionItem
{
    public function execute(
        MeetingActionItem $actionItem,
        User $user,
        ?string $title = null,
        ?string $description = null,
        ?int $assignedToUserId = null,
        ?\DateTimeInterface $dueAt = null,
        ?ActionItemStatus $status = null,
        ?int $sortOrder = null,
        bool $assigneeFieldsProvided = false,
        bool $dueAtProvided = false,
        bool $descriptionProvided = false,
    ): MeetingActionItem {
        Gate::forUser($user)->authorize('update', $actionItem);

        $actionItem->loadMissing('meeting');

        if ($actionItem->team_id !== $user->currentTeam?->id) {
            throw new MeetingsException('You do not belong to this team.');
        }

        $canManage = TeamPermissionGate::allows($user, $actionItem->team_id, 'manage_meetings');
        $previousAssigneeId = $actionItem->assigned_to_user_id;

        if (! $canManage) {
            if ($title !== null || $assigneeFieldsProvided || $dueAtProvided || $descriptionProvided || $sortOrder !== null) {
                throw new MeetingsException('You may only update the status of your assigned action items.');
            }

            if ($status === null) {
                throw new MeetingsException('No changes were provided.');
            }
        }

        if ($canManage) {
            if ($title !== null) {
                if (blank($title)) {
                    throw new MeetingsException('Action item title is required.');
                }

                $actionItem->title = $title;
            }

            if ($descriptionProvided) {
                $actionItem->description = $description;
            }

            if ($assigneeFieldsProvided) {
                app(MeetingActionItemAssigneeService::class)->assertEligible($actionItem->meeting, $assignedToUserId);

                $actionItem->assigned_to_user_id = $assignedToUserId;
            }

            if ($dueAtProvided) {
                $actionItem->due_at = $dueAt;
            }

            if ($sortOrder !== null) {
                $actionItem->sort_order = $sortOrder;
            }
        }

        if ($status !== null) {
            $actionItem->status = $status;

            if ($status === ActionItemStatus::Completed) {
                $actionItem->completed_at = now();
            } elseif ($actionItem->status !== ActionItemStatus::Completed) {
                $actionItem->completed_at = null;
            }
        }

        $actionItem->save();

        $actionItem = $actionItem->fresh(['meeting', 'assignee', 'creator']);

        if ($canManage && $assigneeFieldsProvided && $assignedToUserId !== $previousAssigneeId) {
            $notificationService = app(MeetingActionItemNotificationService::class);

            if ($notificationService->shouldNotifyOnAssign($actionItem->meeting)) {
                $notificationService->syncAssigneeChange($actionItem, $previousAssigneeId, $assignedToUserId);
            }
        }

        MeetingsAuditLogger::actionItemUpdated($actionItem, $user);

        return $actionItem;
    }
}
