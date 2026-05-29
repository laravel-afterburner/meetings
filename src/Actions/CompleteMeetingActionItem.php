<?php

namespace Afterburner\Meetings\Actions;

use Afterburner\Meetings\Enums\ActionItemStatus;
use Afterburner\Meetings\Exceptions\MeetingsException;
use Afterburner\Meetings\Models\MeetingActionItem;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class CompleteMeetingActionItem
{
    public function execute(MeetingActionItem $actionItem, User $user): MeetingActionItem
    {
        Gate::forUser($user)->authorize('complete', $actionItem);

        if ($actionItem->team_id !== $user->currentTeam?->id) {
            throw new MeetingsException('You do not belong to this team.');
        }

        if ($actionItem->status === ActionItemStatus::Completed) {
            return $actionItem;
        }

        if ($actionItem->status === ActionItemStatus::Cancelled) {
            throw new MeetingsException('Cancelled action items cannot be completed.');
        }

        $actionItem->forceFill([
            'status' => ActionItemStatus::Completed,
            'completed_at' => now(),
        ])->save();

        return $actionItem->fresh(['assignee', 'creator']);
    }
}
