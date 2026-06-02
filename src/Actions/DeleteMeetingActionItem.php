<?php

namespace Afterburner\Meetings\Actions;

use Afterburner\Meetings\Exceptions\MeetingsException;
use Afterburner\Meetings\Models\MeetingActionItem;
use Afterburner\Meetings\Support\MeetingsAuditLogger;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class DeleteMeetingActionItem
{
    public function execute(MeetingActionItem $actionItem, User $user): void
    {
        Gate::forUser($user)->authorize('delete', $actionItem);

        if ($actionItem->team_id !== $user->currentTeam?->id) {
            throw new MeetingsException('You do not belong to this team.');
        }

        MeetingsAuditLogger::actionItemDeleted($actionItem, $user);

        $actionItem->delete();
    }
}
