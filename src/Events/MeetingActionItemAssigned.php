<?php

namespace Afterburner\Meetings\Events;

use Afterburner\Meetings\Models\MeetingActionItem;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MeetingActionItemAssigned
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public MeetingActionItem $actionItem) {}
}
