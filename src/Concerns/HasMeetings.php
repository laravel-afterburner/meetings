<?php

namespace Afterburner\Meetings\Concerns;

use Afterburner\Meetings\Models\CalendarEvent;
use Afterburner\Meetings\Models\Meeting;
use Illuminate\Database\Eloquent\Relations\HasMany;

trait HasMeetings
{
    public function meetings(): HasMany
    {
        return $this->hasMany(Meeting::class, 'team_id');
    }

    public function calendarEvents(): HasMany
    {
        return $this->hasMany(CalendarEvent::class, 'team_id');
    }
}
