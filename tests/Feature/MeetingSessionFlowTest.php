<?php

namespace Afterburner\Meetings\Tests\Feature;

use Afterburner\Meetings\Actions\CompleteMeeting;
use Afterburner\Meetings\Actions\CreateMeeting;
use Afterburner\Meetings\Actions\CreateMeetingActionItem;
use Afterburner\Meetings\Actions\StartMeeting;
use Afterburner\Meetings\Actions\UpdateMeeting;
use Afterburner\Meetings\Actions\UpdateMeetingActionItem;
use Afterburner\Meetings\Enums\MeetingStatus;
use Afterburner\Meetings\Enums\MeetingType;
use Afterburner\Meetings\Events\MeetingActionItemAssigned;
use Afterburner\Meetings\Notifications\MeetingActionItemAssignedNotification;
use Afterburner\Meetings\Notifications\MeetingActionItemReassignedNotification;
use Afterburner\Meetings\Tests\TestCase;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

class MeetingSessionFlowTest extends TestCase
{
    public function test_start_meeting_records_attendance_and_moves_to_in_progress(): void
    {
        [$manager, $team] = $this->createTeamWithUser(['manage_meetings']);
        $member = $this->createAdditionalUser($team, [], 'member@example.com');
        $this->attachExistingRole($member, $team, 'manager');

        $meeting = app(CreateMeeting::class)->execute(
            $team,
            $manager,
            'Council meeting',
            MeetingType::Council,
            targetRoleSlugs: ['manager'],
        );

        $meeting = app(UpdateMeeting::class)->execute(
            $meeting,
            $manager,
            $meeting->title,
            $meeting->type,
            MeetingStatus::Scheduled,
            $meeting->location,
            $meeting->virtual_link,
            $meeting->agenda_notes,
            $meeting->scheduled_at,
            $meeting->target_role_slugs,
        );

        $started = app(StartMeeting::class)->execute($meeting, $manager, [
            $member->id => 'present',
        ]);

        $this->assertSame(MeetingStatus::InProgress, $started->status);
        $this->assertDatabaseHas('meeting_attendances', [
            'meeting_id' => $meeting->id,
            'user_id' => $member->id,
            'status' => 'present',
        ]);
    }

    public function test_action_item_notifications_are_deferred_until_meeting_is_finished(): void
    {
        Notification::fake();
        Event::fake([MeetingActionItemAssigned::class]);

        [$manager, $team] = $this->createTeamWithUser(['manage_meetings']);
        $councillor = $this->createAdditionalUser($team, [], 'council@example.com');
        $this->attachExistingRole($councillor, $team, 'manager');

        $meeting = app(CreateMeeting::class)->execute(
            $team,
            $manager,
            'Council meeting',
            MeetingType::Council,
            targetRoleSlugs: ['manager'],
        );

        $meeting = app(UpdateMeeting::class)->execute(
            $meeting,
            $manager,
            $meeting->title,
            $meeting->type,
            MeetingStatus::Scheduled,
            $meeting->location,
            $meeting->virtual_link,
            $meeting->agenda_notes,
            $meeting->scheduled_at,
            $meeting->target_role_slugs,
        );

        $meeting = app(StartMeeting::class)->execute($meeting, $manager, []);

        $actionItem = app(CreateMeetingActionItem::class)->execute(
            $meeting,
            $manager,
            'Follow up on landscaping',
            assignedToUserId: $councillor->id,
        );

        Notification::assertNotSentTo($councillor, MeetingActionItemAssignedNotification::class);
        Event::assertNotDispatched(MeetingActionItemAssigned::class);
        $this->assertNull($actionItem->fresh()->assignee_notified_at);

        app(CompleteMeeting::class)->execute($meeting->fresh(), $manager);

        Notification::assertSentTo($councillor, MeetingActionItemAssignedNotification::class);
        Event::assertDispatched(MeetingActionItemAssigned::class);
        $this->assertNotNull($actionItem->fresh()->assignee_notified_at);
    }

    public function test_reassigning_after_completion_replaces_unread_notification(): void
    {
        [$manager, $team] = $this->createTeamWithUser(['manage_meetings']);
        $firstAssignee = $this->createAdditionalUser($team, [], 'first@example.com');
        $secondAssignee = $this->createAdditionalUser($team, [], 'second@example.com');
        $this->attachExistingRole($firstAssignee, $team, 'manager');
        $this->attachExistingRole($secondAssignee, $team, 'manager');

        $meeting = $this->scheduledMeeting($manager, $team);
        $meeting = app(StartMeeting::class)->execute($meeting, $manager, []);
        $actionItem = app(CreateMeetingActionItem::class)->execute(
            $meeting,
            $manager,
            'Send minutes',
            assignedToUserId: $firstAssignee->id,
        );

        app(CompleteMeeting::class)->execute($meeting->fresh(), $manager);

        $actionItem = $actionItem->fresh();
        $originalNotificationId = $actionItem->assignee_notification_id;
        $this->assertNotNull($originalNotificationId);

        app(UpdateMeetingActionItem::class)->execute(
            $actionItem,
            $manager,
            assigneeFieldsProvided: true,
            assignedToUserId: $secondAssignee->id,
            dueAtProvided: false,
            descriptionProvided: false,
        );

        $this->assertDatabaseMissing('notifications', ['id' => $originalNotificationId]);
        $this->assertDatabaseHas('notifications', [
            'id' => $actionItem->fresh()->assignee_notification_id,
            'notifiable_id' => $secondAssignee->id,
        ]);
    }

    public function test_reassigning_after_read_notification_sends_reassignment_notice(): void
    {
        [$manager, $team] = $this->createTeamWithUser(['manage_meetings']);
        $firstAssignee = $this->createAdditionalUser($team, [], 'first-read@example.com');
        $secondAssignee = $this->createAdditionalUser($team, [], 'second-read@example.com');
        $this->attachExistingRole($firstAssignee, $team, 'manager');
        $this->attachExistingRole($secondAssignee, $team, 'manager');

        $meeting = $this->scheduledMeeting($manager, $team);
        $meeting = app(StartMeeting::class)->execute($meeting, $manager, []);
        $actionItem = app(CreateMeetingActionItem::class)->execute(
            $meeting,
            $manager,
            'Approve budget',
            assignedToUserId: $firstAssignee->id,
        );

        app(CompleteMeeting::class)->execute($meeting->fresh(), $manager);

        $actionItem = $actionItem->fresh();
        $this->assertNotNull($actionItem->assignee_notification_id);

        DB::table('notifications')
            ->where('id', $actionItem->assignee_notification_id)
            ->update(['read_at' => now()]);

        app(UpdateMeetingActionItem::class)->execute(
            $actionItem,
            $manager,
            assigneeFieldsProvided: true,
            assignedToUserId: $secondAssignee->id,
            dueAtProvided: false,
            descriptionProvided: false,
        );

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $secondAssignee->id,
            'type' => MeetingActionItemReassignedNotification::class,
        ]);
    }

    public function test_cannot_start_meeting_unless_scheduled(): void
    {
        [$manager, $team] = $this->createTeamWithUser(['manage_meetings']);

        $meeting = app(CreateMeeting::class)->execute(
            $team,
            $manager,
            'Draft meeting',
            MeetingType::Council,
            targetRoleSlugs: ['manager'],
        );

        $this->expectException(AuthorizationException::class);

        app(StartMeeting::class)->execute($meeting, $manager, []);
    }

    protected function scheduledMeeting($manager, $team)
    {
        $meeting = app(CreateMeeting::class)->execute(
            $team,
            $manager,
            'Council meeting',
            MeetingType::Council,
            targetRoleSlugs: ['manager'],
        );

        return app(UpdateMeeting::class)->execute(
            $meeting,
            $manager,
            $meeting->title,
            $meeting->type,
            MeetingStatus::Scheduled,
            $meeting->location,
            $meeting->virtual_link,
            $meeting->agenda_notes,
            $meeting->scheduled_at,
            $meeting->target_role_slugs,
        );
    }
}
