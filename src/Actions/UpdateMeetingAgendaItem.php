<?php

namespace Afterburner\Meetings\Actions;

use Afterburner\Meetings\Enums\AgendaSection;
use Afterburner\Meetings\Exceptions\MeetingsException;
use Afterburner\Meetings\Models\MeetingAgendaItem;
use Afterburner\Meetings\Support\MeetingsAuditLogger;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class UpdateMeetingAgendaItem
{
    public function execute(
        MeetingAgendaItem $agendaItem,
        User $user,
        string $title,
        ?string $notes = null,
        ?AgendaSection $section = null,
    ): MeetingAgendaItem {
        Gate::forUser($user)->authorize('update', $agendaItem);

        if ($agendaItem->team_id !== $user->currentTeam?->id) {
            throw new MeetingsException('You do not belong to this team.');
        }

        if (blank($title)) {
            throw new MeetingsException('Agenda item title is required.');
        }

        $agendaItem->update([
            'title' => $title,
            'notes' => $notes,
            'section' => $section,
        ]);

        $agendaItem = $agendaItem->fresh(['reference']);

        MeetingsAuditLogger::agendaItemUpdated($agendaItem, $user);

        return $agendaItem;
    }
}
