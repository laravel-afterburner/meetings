<?php

namespace Afterburner\Meetings\Actions;

use Afterburner\Meetings\Enums\AgendaSection;
use Afterburner\Meetings\Exceptions\MeetingsException;
use Afterburner\Meetings\Models\Meeting;
use Afterburner\Meetings\Models\MeetingAgendaItem;
use Afterburner\Meetings\Support\MeetingReferenceRegistry;
use Afterburner\Meetings\Support\MeetingsAuditLogger;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

class CreateMeetingAgendaItem
{
    public function __construct(
        protected MeetingReferenceRegistry $referenceRegistry,
    ) {}

    public function execute(
        Meeting $meeting,
        User $user,
        string $title,
        ?string $notes = null,
        ?AgendaSection $section = null,
        ?Model $reference = null,
        ?int $sortOrder = null,
    ): MeetingAgendaItem {
        Gate::forUser($user)->authorize('create', [MeetingAgendaItem::class, $meeting]);

        if ($meeting->team_id !== $user->currentTeam?->id) {
            throw new MeetingsException('You do not belong to this team.');
        }

        if (blank($title)) {
            throw new MeetingsException('Agenda item title is required.');
        }

        if ($reference !== null) {
            $provider = $this->referenceRegistry->forModel($reference);

            if ($provider === null) {
                throw new MeetingsException('This record type cannot be linked to a meeting agenda.');
            }

            if ((int) $reference->getAttribute('team_id') !== $meeting->team_id) {
                throw new MeetingsException('The linked record does not belong to this team.');
            }

            if (! $provider->canLink($user, $meeting->team, $reference)) {
                throw new MeetingsException('You are not allowed to link this record.');
            }

            $exists = MeetingAgendaItem::query()
                ->where('meeting_id', $meeting->id)
                ->where('reference_type', $reference->getMorphClass())
                ->where('reference_id', $reference->getKey())
                ->exists();

            if ($exists) {
                throw new MeetingsException('This record is already on the meeting agenda.');
            }
        }

        $nextSortOrder = $sortOrder ?? ((int) $meeting->agendaItems()->max('sort_order')) + 1;

        $item = MeetingAgendaItem::query()->create([
            'meeting_id' => $meeting->id,
            'team_id' => $meeting->team_id,
            'title' => $title,
            'notes' => $notes,
            'section' => $section,
            'reference_type' => $reference?->getMorphClass(),
            'reference_id' => $reference?->getKey(),
            'sort_order' => $nextSortOrder,
            'created_by_user_id' => $user->id,
        ]);

        MeetingsAuditLogger::agendaItemCreated($item, $user);

        return $item;
    }
}
