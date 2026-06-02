<?php

namespace Afterburner\Meetings\Actions;

use Afterburner\Meetings\Enums\ActionItemStatus;
use Afterburner\Meetings\Exceptions\MeetingsException;
use Afterburner\Meetings\Models\Meeting;
use Afterburner\Meetings\Models\MeetingActionItem;
use Afterburner\Meetings\Support\MeetingActionItemAssigneeService;
use Afterburner\Meetings\Support\MeetingActionItemNotificationService;
use Afterburner\Meetings\Support\MeetingsAuditLogger;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class CreateMeetingActionItem
{
    public function execute(
        Meeting $meeting,
        User $user,
        string $title,
        ?string $description = null,
        ?int $assignedToUserId = null,
        ?\DateTimeInterface $dueAt = null,
        ?int $sortOrder = null,
    ): MeetingActionItem {
        Gate::forUser($user)->authorize('create', [MeetingActionItem::class, $meeting]);

        if ($meeting->team_id !== $user->currentTeam?->id) {
            throw new MeetingsException('You do not belong to this team.');
        }

        if (blank($title)) {
            throw new MeetingsException('Action item title is required.');
        }

        app(MeetingActionItemAssigneeService::class)->assertEligible($meeting, $assignedToUserId);

        $nextSortOrder = $sortOrder ?? ((int) $meeting->actionItems()->max('sort_order')) + 1;

        $actionItem = MeetingActionItem::query()->create([
            'meeting_id' => $meeting->id,
            'team_id' => $meeting->team_id,
            'title' => $title,
            'description' => $description,
            'assigned_to_user_id' => $assignedToUserId,
            'due_at' => $dueAt,
            'status' => ActionItemStatus::Open,
            'created_by_user_id' => $user->id,
            'sort_order' => $nextSortOrder,
        ]);

        $actionItem = $actionItem->fresh(['meeting', 'assignee', 'creator']);

        if ($assignedToUserId !== null) {
            app(MeetingActionItemNotificationService::class)->notifyAssignee($actionItem);
        }

        MeetingsAuditLogger::actionItemCreated($actionItem, $user);

        return $actionItem;
    }
}
