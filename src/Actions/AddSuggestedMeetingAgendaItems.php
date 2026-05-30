<?php

namespace Afterburner\Meetings\Actions;

use Afterburner\Meetings\Exceptions\MeetingsException;
use Afterburner\Meetings\Models\Meeting;
use Afterburner\Meetings\Models\MeetingAgendaItem;
use Afterburner\Meetings\Support\MeetingReferenceRegistry;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

class AddSuggestedMeetingAgendaItems
{
    public function __construct(
        protected MeetingReferenceRegistry $referenceRegistry,
        protected LinkMeetingAgendaReference $linkMeetingAgendaReference,
    ) {}

    /**
     * @return Collection<int, MeetingAgendaItem>
     */
    public function execute(Meeting $meeting, User $user): Collection
    {
        Gate::forUser($user)->authorize('create', [MeetingAgendaItem::class, $meeting]);

        if ($meeting->team_id !== $user->currentTeam?->id) {
            throw new MeetingsException('You do not belong to this team.');
        }

        $linkedReferenceKeys = MeetingAgendaItem::query()
            ->where('meeting_id', $meeting->id)
            ->whereNotNull('reference_type')
            ->get(['reference_type', 'reference_id'])
            ->map(fn (MeetingAgendaItem $item) => $item->reference_type.':'.$item->reference_id)
            ->all();

        $created = collect();

        foreach ($this->referenceRegistry->available() as $provider) {
            $suggestions = $provider->suggestions($meeting->team, $user, $meeting->type, $meeting);

            foreach ($suggestions as $reference) {
                $key = $reference->getMorphClass().':'.$reference->getKey();

                if (in_array($key, $linkedReferenceKeys, true)) {
                    continue;
                }

                if (! $provider->canLink($user, $meeting->team, $reference)) {
                    continue;
                }

                try {
                    $created->push($this->linkMeetingAgendaReference->execute(
                        $meeting,
                        $user,
                        $provider->key(),
                        (int) $reference->getKey(),
                    ));

                    $linkedReferenceKeys[] = $key;
                } catch (MeetingsException) {
                    continue;
                }
            }
        }

        return $created;
    }
}
