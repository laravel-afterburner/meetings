<?php

namespace Afterburner\Meetings\Support;

use Afterburner\Meetings\Enums\ActionItemStatus;
use Afterburner\Meetings\Models\Meeting;

class MeetingOpenItemsChecker
{
    public function hasOpenItems(Meeting $meeting): bool
    {
        return $this->openActionItemCount($meeting) > 0;
    }

    public function openActionItemCount(Meeting $meeting): int
    {
        return $meeting->actionItems()
            ->whereIn('status', [
                ActionItemStatus::Open->value,
                ActionItemStatus::InProgress->value,
            ])
            ->count();
    }

    /**
     * @return list<string>
     */
    public function warnings(Meeting $meeting): array
    {
        $warnings = [];
        $openActionItems = $this->openActionItemCount($meeting);

        if ($openActionItems > 0) {
            $warnings[] = $openActionItems === 1
                ? '1 action item is still open or in progress.'
                : "{$openActionItems} action items are still open or in progress.";
        }

        if ($meeting->minutes_finalized_at === null && filled($meeting->minutes)) {
            $warnings[] = 'Meeting minutes are saved as a draft but not finalized.';
        }

        return $warnings;
    }
}
