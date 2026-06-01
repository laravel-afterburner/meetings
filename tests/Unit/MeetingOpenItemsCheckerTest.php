<?php

namespace Afterburner\Meetings\Tests\Unit;

use Afterburner\Meetings\Actions\CreateMeeting;
use Afterburner\Meetings\Actions\CreateMeetingActionItem;
use Afterburner\Meetings\Enums\ActionItemStatus;
use Afterburner\Meetings\Enums\MeetingType;
use Afterburner\Meetings\Support\MeetingOpenItemsChecker;
use Afterburner\Meetings\Tests\TestCase;

class MeetingOpenItemsCheckerTest extends TestCase
{
    public function test_detects_open_action_items(): void
    {
        [$manager, $team] = $this->createTeamWithUser(['manage_meetings']);
        $assignee = $this->createAdditionalUser($team, ['manage_meetings'], 'assignee@example.com');

        $meeting = app(CreateMeeting::class)->execute(
            $team,
            $manager,
            'Council meeting',
            MeetingType::Council,
            targetRoleSlugs: ['manager'],
        );

        app(CreateMeetingActionItem::class)->execute(
            $meeting,
            $manager,
            'Follow up on budget',
            $assignee->id,
        );

        $checker = app(MeetingOpenItemsChecker::class);

        $this->assertTrue($checker->hasOpenItems($meeting->fresh()));
        $this->assertNotEmpty($checker->warnings($meeting->fresh()));
    }

    public function test_ignores_completed_action_items(): void
    {
        [$manager, $team] = $this->createTeamWithUser(['manage_meetings']);
        $assignee = $this->createAdditionalUser($team, ['manage_meetings'], 'assignee@example.com');

        $meeting = app(CreateMeeting::class)->execute(
            $team,
            $manager,
            'Council meeting',
            MeetingType::Council,
            targetRoleSlugs: ['manager'],
        );

        $item = app(CreateMeetingActionItem::class)->execute(
            $meeting,
            $manager,
            'Follow up on budget',
            $assignee->id,
        );

        $item->update(['status' => ActionItemStatus::Completed, 'completed_at' => now()]);

        $checker = app(MeetingOpenItemsChecker::class);

        $this->assertFalse($checker->hasOpenItems($meeting->fresh()));
    }
}
