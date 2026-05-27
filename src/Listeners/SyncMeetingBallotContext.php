<?php

namespace Afterburner\Meetings\Listeners;

use Afterburner\Meetings\Models\MeetingBallot;
use Afterburner\Voting\Events\BallotClosed;
use Afterburner\Voting\Events\BallotPublished;

class SyncMeetingBallotContext
{
    public function handle(BallotPublished|BallotClosed $event): void
    {
        $ballot = $event->ballot;
        $eventName = $event instanceof BallotPublished ? 'published' : 'closed';

        MeetingBallot::query()
            ->where('ballot_id', $ballot->id)
            ->with('meeting')
            ->get()
            ->each(function (MeetingBallot $link) use ($ballot, $eventName) {
                if ($link->meeting && $link->meeting->team_id === $ballot->team_id) {
                    $link->meeting->recordBallotEvent($ballot->id, $eventName);
                }
            });
    }
}
