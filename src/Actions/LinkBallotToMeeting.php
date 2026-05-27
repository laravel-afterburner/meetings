<?php

namespace Afterburner\Meetings\Actions;

use Afterburner\Meetings\Exceptions\MeetingsException;
use Afterburner\Meetings\Models\Meeting;
use Afterburner\Meetings\Models\MeetingBallot;
use Afterburner\Meetings\Support\VotingIntegration;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class LinkBallotToMeeting
{
    public function execute(Meeting $meeting, int $ballotId, User $user): MeetingBallot
    {
        Gate::forUser($user)->authorize('linkBallots', $meeting);

        if (! VotingIntegration::isEnabled()) {
            throw new MeetingsException('Ballot linking is not available.');
        }

        $ballotClass = VotingIntegration::ballotModelClass();
        $ballot = $ballotClass::query()
            ->where('team_id', $meeting->team_id)
            ->find($ballotId);

        if (! $ballot) {
            throw new MeetingsException('The ballot must belong to the same team as this meeting.');
        }

        return MeetingBallot::query()->firstOrCreate(
            [
                'meeting_id' => $meeting->id,
                'ballot_id' => $ballot->id,
            ],
            [
                'linked_by_user_id' => $user->id,
            ]
        );
    }
}
