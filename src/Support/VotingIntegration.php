<?php

namespace Afterburner\Meetings\Support;

use Afterburner\Voting\Models\Ballot;
use Illuminate\Support\Facades\Schema;

class VotingIntegration
{
    public static function isAvailable(): bool
    {
        return class_exists(Ballot::class)
            && Schema::hasTable('ballots')
            && Schema::hasTable('meeting_ballots');
    }

    public static function isEnabled(): bool
    {
        if (! config('afterburner-meetings.voting_enabled', true)) {
            return false;
        }

        return static::isAvailable();
    }

    public static function ballotModelClass(): string
    {
        return Ballot::class;
    }
}
