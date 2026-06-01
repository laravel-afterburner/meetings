<?php

namespace Afterburner\Meetings\Tests\Feature;

use Afterburner\Meetings\Actions\CompleteMeeting;
use Afterburner\Meetings\Actions\CreateMeeting;
use Afterburner\Meetings\Actions\StartMeeting;
use Afterburner\Meetings\Actions\UpdateMeeting;
use Afterburner\Meetings\Actions\UpdateMeetingMinutes;
use Afterburner\Meetings\Enums\MeetingStatus;
use Afterburner\Meetings\Enums\MeetingType;
use Afterburner\Meetings\Livewire\Meetings\Create;
use Afterburner\Meetings\Livewire\Meetings\Show;
use Afterburner\Meetings\Models\Meeting;
use Afterburner\Meetings\Tests\TestCase;
use Illuminate\Support\Facades\Auth;

class MeetingCreateLivewireTest extends TestCase
{
    public function test_changing_meeting_type_preserves_selected_audience_groups(): void
    {
        config([
            'afterburner-meetings.default_target_roles_by_type' => [
                'agm' => ['manager'],
                'council' => ['secretary'],
                'special' => [],
            ],
        ]);

        $component = new Create;
        $component->targetRoleSlugs = ['manager', 'secretary'];

        $component->updatedType('council');

        $this->assertSame(['manager', 'secretary'], $component->targetRoleSlugs);
    }

    public function test_changing_meeting_type_applies_defaults_when_no_groups_selected(): void
    {
        config([
            'afterburner-meetings.default_target_roles_by_type' => [
                'agm' => ['manager'],
                'council' => ['secretary'],
                'special' => [],
            ],
        ]);

        $component = new Create;
        $component->targetRoleSlugs = [];

        $component->updatedType('council');

        $this->assertSame(['secretary'], $component->targetRoleSlugs);
    }

    public function test_save_draft_persists_selected_audience_groups(): void
    {
        [$user, $team] = $this->createTeamWithUser(['manage_meetings']);
        $this->createRoleWithPermissions('secretary', ['manage_meetings']);
        Auth::login($user);

        config([
            'afterburner-meetings.default_target_roles_by_type' => [
                'agm' => [],
                'council' => [],
                'special' => [],
            ],
        ]);

        $component = new Create;
        $component->teamId = $team->id;
        $component->title = 'Council meeting';
        $component->targetRoleSlugs = ['manager', 'secretary'];

        $component->saveDraft();

        $savedMeeting = Meeting::query()
            ->where('team_id', $team->id)
            ->where('title', 'Council meeting')
            ->first();

        $this->assertNotNull($savedMeeting);
        $this->assertSame(['manager', 'secretary'], $savedMeeting->target_role_slugs);
    }

    public function test_save_draft_on_new_meeting_persists_record(): void
    {
        [$user, $team] = $this->createTeamWithUser(['manage_meetings']);
        Auth::login($user);

        $component = new Create;
        $component->teamId = $team->id;
        $component->title = 'New council meeting';
        $component->targetRoleSlugs = ['manager'];

        try {
            $component->saveDraft();
        } catch (\Throwable) {
            // Livewire halts execution when redirecting.
        }

        $meeting = Meeting::query()
            ->where('team_id', $team->id)
            ->where('title', 'New council meeting')
            ->first();

        $this->assertNotNull($meeting);
        $this->assertSame(MeetingStatus::Draft, $meeting->status);
    }

    public function test_schedule_meeting_sets_status_to_scheduled(): void
    {
        [$user, $team] = $this->createTeamWithUser(['manage_meetings']);
        Auth::login($user);

        $meeting = app(CreateMeeting::class)->execute(
            $team,
            $user,
            'Draft meeting',
            MeetingType::Council,
            targetRoleSlugs: ['manager'],
        );

        $component = new Create;
        $component->teamId = $team->id;
        $component->meetingId = $meeting->id;
        $component->title = 'Draft meeting';
        $component->type = 'council';
        $component->virtualLink = '';
        $component->targetRoleSlugs = ['manager'];

        $component->scheduleMeeting();

        $this->assertSame(MeetingStatus::Scheduled, $meeting->fresh()->status);
    }

    public function test_mount_marks_component_as_opened_for_edit_when_meeting_id_provided(): void
    {
        [$user, $team] = $this->createTeamWithUser(['manage_meetings']);
        Auth::login($user);

        $meeting = app(CreateMeeting::class)->execute(
            $team,
            $user,
            'Council meeting',
            MeetingType::Council,
            targetRoleSlugs: ['manager'],
        );

        $component = new Create;
        $component->mount($team, $meeting->id);

        $this->assertTrue($component->openedAsEdit);
        $this->assertSame($meeting->id, $component->meetingId);
    }

    public function test_completed_meeting_edit_uses_details_only_mode(): void
    {
        [$user, $team] = $this->createTeamWithUser(['manage_meetings']);
        Auth::login($user);

        $meeting = app(CreateMeeting::class)->execute(
            $team,
            $user,
            'Council meeting',
            MeetingType::Council,
            targetRoleSlugs: ['manager'],
        );

        $meeting = app(UpdateMeeting::class)->execute(
            $meeting,
            $user,
            $meeting->title,
            $meeting->type,
            MeetingStatus::Scheduled,
            $meeting->location,
            $meeting->virtual_link,
            $meeting->agenda_notes,
            $meeting->scheduled_at,
            $meeting->target_role_slugs,
        );

        $meeting = app(StartMeeting::class)->execute($meeting, $user, []);
        $meeting = app(CompleteMeeting::class)->execute($meeting, $user);

        $component = new Create;
        $component->mount($team, $meeting->id);

        $this->assertTrue($component->detailsOnly);
    }

    public function test_completed_meeting_details_can_be_updated(): void
    {
        [$user, $team] = $this->createTeamWithUser(['manage_meetings']);
        Auth::login($user);

        $meeting = app(CreateMeeting::class)->execute(
            $team,
            $user,
            'Council meeting',
            MeetingType::Council,
            targetRoleSlugs: ['manager'],
        );

        $meeting = app(UpdateMeeting::class)->execute(
            $meeting,
            $user,
            $meeting->title,
            $meeting->type,
            MeetingStatus::Scheduled,
            'Old hall',
            $meeting->virtual_link,
            $meeting->agenda_notes,
            $meeting->scheduled_at,
            $meeting->target_role_slugs,
        );

        $meeting = app(StartMeeting::class)->execute($meeting, $user, []);
        $meeting = app(CompleteMeeting::class)->execute($meeting, $user);

        $updated = app(UpdateMeeting::class)->execute(
            $meeting->fresh(),
            $user,
            'Updated council meeting',
            $meeting->type,
            MeetingStatus::Completed,
            'New hall',
            $meeting->virtual_link,
            $meeting->agenda_notes,
            $meeting->scheduled_at,
            $meeting->target_role_slugs,
        );

        $this->assertSame('Updated council meeting', $updated->title);
        $this->assertSame('New hall', $updated->location);
    }

    public function test_minutes_can_be_finalized_after_meeting_is_completed(): void
    {
        [$user, $team] = $this->createTeamWithUser(['manage_meetings']);
        Auth::login($user);

        $meeting = app(CreateMeeting::class)->execute(
            $team,
            $user,
            'Council meeting',
            MeetingType::Council,
            targetRoleSlugs: ['manager'],
        );

        $meeting = app(UpdateMeeting::class)->execute(
            $meeting,
            $user,
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
            $user,
            'Draft minutes text.',
            false,
        );

        $meeting = app(CompleteMeeting::class)->execute($meeting->fresh(), $user);

        app(UpdateMeetingMinutes::class)->execute(
            $meeting->fresh(),
            $user,
            'Draft minutes text.',
            true,
        );

        $meeting->refresh();
        $this->assertNotNull($meeting->minutes_finalized_at);
    }
}
