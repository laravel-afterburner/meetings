<?php

namespace Afterburner\Meetings\Events;

use Afterburner\Meetings\Models\Meeting;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MeetingScheduled
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public Meeting $meeting) {}
}
