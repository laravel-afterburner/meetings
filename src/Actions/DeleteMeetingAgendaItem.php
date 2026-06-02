<?php

namespace Afterburner\Meetings\Actions;

use Afterburner\Meetings\Exceptions\MeetingsException;
use Afterburner\Meetings\Models\MeetingAgendaItem;
use Afterburner\Meetings\Support\MeetingsAuditLogger;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class DeleteMeetingAgendaItem
{
    public function execute(MeetingAgendaItem $agendaItem, User $user): void
    {
        Gate::forUser($user)->authorize('delete', $agendaItem);

        if ($agendaItem->team_id !== $user->currentTeam?->id) {
            throw new MeetingsException('You do not belong to this team.');
        }

        MeetingsAuditLogger::agendaItemDeleted($agendaItem, $user);

        $agendaItem->delete();
    }
}
