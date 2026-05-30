<?php

namespace Afterburner\Meetings\Actions;

use Afterburner\Meetings\Enums\AgendaSection;
use Afterburner\Meetings\Exceptions\MeetingsException;
use Afterburner\Meetings\Models\Meeting;
use Afterburner\Meetings\Models\MeetingAgendaItem;
use Afterburner\Meetings\Support\MeetingReferenceRegistry;
use App\Models\User;

class LinkMeetingAgendaReference
{
    public function __construct(
        protected MeetingReferenceRegistry $referenceRegistry,
        protected CreateMeetingAgendaItem $createMeetingAgendaItem,
    ) {}

    public function execute(
        Meeting $meeting,
        User $user,
        string $providerKey,
        int $referenceId,
        ?AgendaSection $section = null,
        ?string $notes = null,
    ): MeetingAgendaItem {
        $provider = $this->referenceRegistry->get($providerKey);

        if ($provider === null || ! $provider->isAvailable()) {
            throw new MeetingsException('This reference type is not available.');
        }

        $reference = $this->referenceRegistry->resolveReference($providerKey, $meeting->team, $referenceId);

        if ($reference === null) {
            throw new MeetingsException('The selected record could not be found.');
        }

        return $this->createMeetingAgendaItem->execute(
            $meeting,
            $user,
            $provider->agendaTitle($reference),
            $notes ?? $provider->agendaSummary($reference),
            $section,
            $reference,
        );
    }
}
