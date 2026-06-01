<?php

namespace Afterburner\Meetings\Tests\Feature;

use Afterburner\Meetings\Actions\CreateMeeting;
use Afterburner\Meetings\Actions\UpdateMeeting;
use Afterburner\Meetings\Enums\MeetingStatus;
use Afterburner\Meetings\Enums\MeetingType;
use Afterburner\Meetings\Livewire\Meetings\Show;
use Afterburner\Meetings\Models\Meeting;
use Afterburner\Meetings\Tests\TestCase;
use Illuminate\Support\Facades\Auth;

class MeetingShowLivewireTest extends TestCase
{
    public function test_start_meeting_uses_roll_call_flow(): void
    {
        [$manager, $team] = $this->createTeamWithUser(['manage_meetings']);
        $member = $this->createAdditionalUser($team, [], 'member@example.com');
        $this->attachExistingRole($member, $team, 'manager');
        Auth::login($manager);

        $meeting = $this->scheduledMeeting($manager, $team);

        $component = new Show;
        $component->teamId = $team->id;
        $component->meetingId = $meeting->id;

        $component->openRollCall();
        $this->assertTrue($component->showRollCallModal);
        $component->rollCallAttendance = [$member->id => 'present'];
        $component->saveRollCallAndStart();

        $this->assertSame(MeetingStatus::InProgress, $meeting->fresh()->status);
        $this->assertDatabaseHas('meeting_attendances', [
            'meeting_id' => $meeting->id,
            'user_id' => $member->id,
            'status' => 'present',
        ]);
    }

    public function test_in_progress_meeting_does_not_redirect_members_without_session_access(): void
    {
        [$manager, $team] = $this->createTeamWithUser(['manage_meetings']);
        $this->createRoleWithPermissions('observer', []);
        $member = $this->createAdditionalUser($team, [], 'observer@example.com');
        $this->attachExistingRole($member, $team, 'observer');
        $member->update(['current_team_id' => $team->id]);
        Auth::login($member);

        $meeting = $this->scheduledMeeting($manager, $team);
        app(\Afterburner\Meetings\Actions\StartMeeting::class)->execute($meeting, $manager, []);

        $component = new Show;
        $component->mount($team, $meeting->fresh());

        $this->assertSame($team->id, $component->teamId);
        $this->assertSame($meeting->id, $component->meetingId);
    }

    protected function scheduledMeeting($manager, $team): Meeting
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
