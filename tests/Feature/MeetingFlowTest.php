<?php

namespace Afterburner\Meetings\Tests\Feature;

use Afterburner\Meetings\Actions\CompleteMeeting;
use Afterburner\Meetings\Actions\CompleteMeetingActionItem;
use Afterburner\Meetings\Actions\CreateMeeting;
use Afterburner\Meetings\Actions\CreateMeetingActionItem;
use Afterburner\Meetings\Actions\DeleteMeetingActionItem;
use Afterburner\Meetings\Actions\LinkBallotToMeeting;
use Afterburner\Meetings\Actions\RecordAttendance;
use Afterburner\Meetings\Actions\StartMeeting;
use Afterburner\Meetings\Actions\UpdateMeeting;
use Afterburner\Meetings\Actions\UpdateMeetingActionItem;
use Afterburner\Meetings\Actions\UpdateMeetingMinutes;
use Afterburner\Meetings\Enums\ActionItemStatus;
use Afterburner\Meetings\Enums\AttendanceStatus;
use Afterburner\Meetings\Enums\MeetingStatus;
use Afterburner\Meetings\Enums\MeetingType;
use Afterburner\Meetings\Events\MeetingActionItemAssigned;
use Afterburner\Meetings\Events\MeetingScheduled;
use Afterburner\Meetings\Exceptions\MeetingsException;
use Afterburner\Meetings\Listeners\NotifyMeetingAudience;
use Afterburner\Meetings\Listeners\SyncMeetingBallotContext;
use Afterburner\Meetings\Models\Meeting;
use Afterburner\Meetings\Models\MeetingActionItem;
use Afterburner\Meetings\Notifications\MeetingActionItemAssignedNotification;
use Afterburner\Meetings\Notifications\MeetingScheduledNotification;
use Afterburner\Meetings\Support\AttendanceRecorderResolver;
use Afterburner\Meetings\Tests\TestCase;
use Afterburner\Voting\Actions\CreateBallot;
use Afterburner\Voting\Actions\PublishBallot;
use Afterburner\Voting\Enums\BallotType;
use Afterburner\Voting\Enums\ElectorateType;
use Afterburner\Voting\Events\BallotPublished;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

class MeetingFlowTest extends TestCase
{
    public function test_create_and_update_meeting(): void
    {
        [$user, $team] = $this->createTeamWithUser(['manage_meetings']);

        $meeting = app(CreateMeeting::class)->execute(
            $team,
            $user,
            'Annual General Meeting',
            MeetingType::Agm,
            'Community hall',
            'https://meet.example.com/agm',
            'Budget review and elections',
            now()->addWeek(),
            ['manager'],
        );

        $this->assertSame(MeetingStatus::Draft, $meeting->status);
        $this->assertSame('Annual General Meeting', $meeting->title);
        $this->assertSame(['manager'], $meeting->target_role_slugs);
        $this->assertDatabaseHas('meetings', [
            'id' => $meeting->id,
            'team_id' => $team->id,
            'type' => 'agm',
        ]);
    }

    public function test_record_attendance_for_invited_team_member(): void
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

        app(RecordAttendance::class)->execute(
            $meeting,
            $manager,
            $member->id,
            AttendanceStatus::Present,
        );

        $this->assertDatabaseHas('meeting_attendances', [
            'meeting_id' => $meeting->id,
            'user_id' => $member->id,
            'status' => 'present',
        ]);
    }

    public function test_scheduling_meeting_notifies_invited_users(): void
    {
        Notification::fake();

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

        $freshMeeting = $meeting->fresh(['team']);

        app(NotifyMeetingAudience::class)
            ->handle(new MeetingScheduled($freshMeeting));

        Notification::assertSentTo($member, MeetingScheduledNotification::class);
        Notification::assertNotSentTo($manager, MeetingScheduledNotification::class);
        $this->assertNotNull($freshMeeting->fresh()->invitations_sent_at);
    }

    public function test_update_meeting_to_scheduled_notifies_invited_users(): void
    {
        Notification::fake();

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

        app(UpdateMeeting::class)->execute(
            $meeting,
            $manager,
            $meeting->title,
            $meeting->type,
            MeetingStatus::Scheduled,
            null,
            null,
            null,
            now()->addWeek(),
            ['manager'],
        );

        Notification::assertSentTo($member, MeetingScheduledNotification::class);
    }

    public function test_attendance_recorder_falls_back_when_secretary_not_present(): void
    {
        [$secretary, $team] = $this->createTeamWithUser(['manage_meetings'], 'secretary');
        $president = $this->createAdditionalUser($team, ['manage_meetings'], 'president@example.com');

        $this->assignRole($secretary, 'secretary', $team->id);
        $this->assignRole($president, 'president', $team->id);

        $meeting = app(CreateMeeting::class)->execute(
            $team,
            $secretary,
            'Council meeting',
            MeetingType::Council,
            targetRoleSlugs: ['secretary', 'president'],
        );

        app(UpdateMeeting::class)->execute(
            $meeting,
            $secretary,
            $meeting->title,
            $meeting->type,
            MeetingStatus::InProgress,
            null,
            null,
            null,
            null,
            ['secretary', 'president'],
        );

        app(RecordAttendance::class)->execute(
            $meeting->fresh(),
            $secretary,
            $president->id,
            AttendanceStatus::Present,
        );

        $recorder = app(AttendanceRecorderResolver::class)->recorderFor($meeting->fresh());

        $this->assertSame($president->id, $recorder?->id);
        $this->assertTrue(app(AttendanceRecorderResolver::class)->canRecord($president, $meeting->fresh()));
        $this->assertFalse(app(AttendanceRecorderResolver::class)->canRecord($secretary, $meeting->fresh()));
    }

    public function test_meeting_minutes_can_be_saved_and_finalized(): void
    {
        [$manager, $team] = $this->createTeamWithUser(['manage_meetings']);

        $meeting = app(CreateMeeting::class)->execute(
            $team,
            $manager,
            'Council meeting',
            MeetingType::Council,
            targetRoleSlugs: ['manager'],
        );

        app(UpdateMeeting::class)->execute(
            $meeting,
            $manager,
            $meeting->title,
            $meeting->type,
            MeetingStatus::InProgress,
            null,
            null,
            null,
            null,
            ['manager'],
        );

        app(UpdateMeetingMinutes::class)->execute(
            $meeting->fresh(),
            $manager,
            'Motion carried unanimously.',
            true,
        );

        $meeting->refresh();
        $this->assertSame('Motion carried unanimously.', $meeting->minutes);
        $this->assertNotNull($meeting->minutes_finalized_at);
        $this->assertSame($manager->id, $meeting->minutes_finalized_by_user_id);
    }

    public function test_link_ballot_and_sync_context_on_publish(): void
    {
        Event::fake([BallotPublished::class]);

        [$user, $team] = $this->createTeamWithUser(['manage_meetings', 'create_resolutions']);

        $meeting = app(CreateMeeting::class)->execute(
            $team,
            $user,
            'AGM with resolutions',
            MeetingType::Agm,
            targetRoleSlugs: ['manager'],
        );

        $ballot = app(CreateBallot::class)->execute(
            $team,
            $user,
            'Approve budget',
            null,
            BallotType::Resolution,
            ElectorateType::AllMembers,
            [
                ['label' => 'Yes'],
                ['label' => 'No'],
            ],
            null,
            null,
            now()->subHour(),
            now()->addWeek(),
        );

        app(LinkBallotToMeeting::class)->execute($meeting, $ballot->id, $user);

        $this->assertDatabaseHas('meeting_ballots', [
            'meeting_id' => $meeting->id,
            'ballot_id' => $ballot->id,
        ]);

        $published = app(PublishBallot::class)->execute($ballot, $user);
        app(SyncMeetingBallotContext::class)->handle(new BallotPublished($published));

        $meeting->refresh();
        $this->assertSame('published', $meeting->settings['ballot_events'][(string) $ballot->id]['event']);
    }

    public function test_secretary_can_create_and_manage_meeting_action_items(): void
    {
        Notification::fake();
        Event::fake([MeetingActionItemAssigned::class]);

        [$secretary, $team] = $this->createTeamWithUser(['manage_meetings'], 'secretary@example.com');
        $councillor = $this->createAdditionalUser($team, [], 'council@example.com');
        $this->attachExistingRole($councillor, $team, 'manager');

        $meeting = app(CreateMeeting::class)->execute(
            $team,
            $secretary,
            'Council meeting',
            MeetingType::Council,
            targetRoleSlugs: ['manager'],
        );

        $meeting = app(UpdateMeeting::class)->execute(
            $meeting,
            $secretary,
            $meeting->title,
            $meeting->type,
            MeetingStatus::Scheduled,
            $meeting->location,
            $meeting->virtual_link,
            $meeting->agenda_notes,
            $meeting->scheduled_at,
            $meeting->target_role_slugs,
        );

        $meeting = app(StartMeeting::class)->execute($meeting, $secretary, []);

        $actionItem = app(CreateMeetingActionItem::class)->execute(
            $meeting,
            $secretary,
            'Send revised budget to owners',
            'Include landscaping line item updates.',
            $councillor->id,
            now()->addWeek(),
        );

        $this->assertDatabaseHas('meeting_action_items', [
            'id' => $actionItem->id,
            'meeting_id' => $meeting->id,
            'team_id' => $team->id,
            'assigned_to_user_id' => $councillor->id,
            'status' => 'open',
        ]);

        Event::assertNotDispatched(MeetingActionItemAssigned::class);

        app(CompleteMeeting::class)->execute($meeting->fresh(), $secretary);

        Event::assertDispatched(MeetingActionItemAssigned::class);
        Notification::assertSentTo($councillor, MeetingActionItemAssignedNotification::class);

        app(UpdateMeetingActionItem::class)->execute(
            $actionItem,
            $secretary,
            'Send revised budget to owners (v2)',
            assigneeFieldsProvided: false,
            dueAtProvided: false,
            descriptionProvided: false,
        );

        $this->assertSame('Send revised budget to owners (v2)', $actionItem->fresh()->title);

        app(CompleteMeetingActionItem::class)->execute($actionItem->fresh(), $secretary);

        $completed = $actionItem->fresh();
        $this->assertSame(ActionItemStatus::Completed, $completed->status);
        $this->assertNotNull($completed->completed_at);

        app(DeleteMeetingActionItem::class)->execute($completed, $secretary);

        $this->assertDatabaseMissing('meeting_action_items', ['id' => $actionItem->id]);
    }

    public function test_assignee_can_complete_own_action_item(): void
    {
        [$secretary, $team] = $this->createTeamWithUser(['manage_meetings'], 'secretary@example.com');
        $this->createRoleWithPermissions('councillor', []);
        $councillor = $this->createAdditionalUser($team, [], 'council@example.com');
        $councillor->update(['current_team_id' => $team->id]);
        $this->attachExistingRole($councillor, $team, 'councillor');

        $meeting = app(CreateMeeting::class)->execute(
            $team,
            $secretary,
            'Council meeting',
            MeetingType::Council,
            targetRoleSlugs: ['councillor'],
        );

        $actionItem = app(CreateMeetingActionItem::class)->execute(
            $meeting,
            $secretary,
            'Review insurance quote',
            assignedToUserId: $councillor->id,
        );

        app(UpdateMeetingActionItem::class)->execute(
            $actionItem,
            $councillor,
            status: ActionItemStatus::InProgress,
        );

        app(CompleteMeetingActionItem::class)->execute($actionItem->fresh(), $councillor);

        $this->assertSame(ActionItemStatus::Completed, $actionItem->fresh()->status);
    }

    public function test_assignee_cannot_edit_other_fields_on_action_item(): void
    {
        [$secretary, $team] = $this->createTeamWithUser(['manage_meetings'], 'secretary@example.com');
        $this->createRoleWithPermissions('councillor', []);
        $councillor = $this->createAdditionalUser($team, [], 'council@example.com');
        $councillor->update(['current_team_id' => $team->id]);
        $this->attachExistingRole($councillor, $team, 'councillor');

        $meeting = app(CreateMeeting::class)->execute(
            $team,
            $secretary,
            'Council meeting',
            MeetingType::Council,
            targetRoleSlugs: ['councillor'],
        );

        $actionItem = app(CreateMeetingActionItem::class)->execute(
            $meeting,
            $secretary,
            'Book elevator inspection',
            assignedToUserId: $councillor->id,
        );

        $this->expectException(MeetingsException::class);

        app(UpdateMeetingActionItem::class)->execute(
            $actionItem,
            $councillor,
            'Changed title',
        );
    }

    public function test_meeting_index_counts_overdue_action_items(): void
    {
        [$secretary, $team] = $this->createTeamWithUser(['manage_meetings'], 'secretary@example.com');

        $meeting = app(CreateMeeting::class)->execute(
            $team,
            $secretary,
            'Council meeting',
            MeetingType::Council,
            targetRoleSlugs: ['manager'],
        );

        MeetingActionItem::query()->create([
            'meeting_id' => $meeting->id,
            'team_id' => $team->id,
            'title' => 'Overdue task',
            'status' => ActionItemStatus::Open,
            'created_by_user_id' => $secretary->id,
            'due_at' => now()->subDay(),
            'sort_order' => 1,
        ]);

        MeetingActionItem::query()->create([
            'meeting_id' => $meeting->id,
            'team_id' => $team->id,
            'title' => 'Completed task',
            'status' => ActionItemStatus::Completed,
            'created_by_user_id' => $secretary->id,
            'due_at' => now()->subDay(),
            'completed_at' => now(),
            'sort_order' => 2,
        ]);

        $count = Meeting::query()
            ->forTeam($team->id)
            ->withCount(['actionItems as overdue_action_items_count' => fn ($query) => $query->overdue()])
            ->findOrFail($meeting->id)
            ->overdue_action_items_count;

        $this->assertSame(1, $count);
    }

    protected function assignRole(User $user, string $slug, int $teamId): void
    {
        $roleId = $this->createRoleWithPermissions($slug, ['manage_meetings']);

        DB::table('user_role')->insert([
            'user_id' => $user->id,
            'role_id' => $roleId,
            'team_id' => $teamId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
