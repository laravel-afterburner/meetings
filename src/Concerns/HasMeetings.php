<?php

namespace Afterburner\Meetings\Concerns;

use Afterburner\Meetings\Models\Meeting;
use Illuminate\Database\Eloquent\Relations\HasMany;

trait HasMeetings
{
    public function meetings(): HasMany
    {
        return $this->hasMany(Meeting::class, 'team_id');
    }
}
