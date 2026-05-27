<?php

namespace Afterburner\Meetings\Actions;

use Afterburner\Meetings\Exceptions\MeetingsException;
use Afterburner\Meetings\Models\Meeting;
use Afterburner\Meetings\Models\MeetingBallot;
use Afterburner\Meetings\Support\VotingIntegration;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class UnlinkBallotFromMeeting
{
    public function execute(Meeting $meeting, int $ballotId, User $user): void
    {
        Gate::forUser($user)->authorize('linkBallots', $meeting);

        if (! VotingIntegration::isEnabled()) {
            throw new MeetingsException('Ballot linking is not available.');
        }

        $link = MeetingBallot::query()
            ->where('meeting_id', $meeting->id)
            ->where('ballot_id', $ballotId)
            ->first();

        if (! $link) {
            throw new MeetingsException('Ballot is not linked to this meeting.');
        }

        $link->delete();
    }
}
