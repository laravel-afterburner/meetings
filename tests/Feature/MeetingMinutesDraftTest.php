<?php

namespace Afterburner\Meetings\Tests\Feature;

use Afterburner\Meetings\Actions\BuildMeetingMinutesDraft;
use Afterburner\Meetings\Actions\CreateMeeting;
use Afterburner\Meetings\Actions\CreateMeetingActionItem;
use Afterburner\Meetings\Actions\LinkBallotToMeeting;
use Afterburner\Meetings\Actions\RecordAttendance;
use Afterburner\Meetings\Actions\UpdateMeeting;
use Afterburner\Meetings\Enums\AttendanceStatus;
use Afterburner\Meetings\Enums\MeetingStatus;
use Afterburner\Meetings\Enums\MeetingType;
use Afterburner\Meetings\Tests\TestCase;
use Afterburner\Voting\Actions\CastVote;
use Afterburner\Voting\Actions\CloseBallot;
use Afterburner\Voting\Actions\CreateBallot;
use Afterburner\Voting\Actions\PublishBallot;
use Afterburner\Voting\Enums\BallotType;
use Afterburner\Voting\Enums\ElectorateType;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class MeetingMinutesDraftTest extends TestCase
{
    public function test_draft_includes_attendance_quorum_resolutions_and_action_items(): void
    {
        [$manager, $team] = $this->createTeamWithUser(['manage_meetings', 'create_resolutions', 'vote_resolutions', 'view_ballot_results']);
        $member = $this->createAdditionalUser($team, ['vote_resolutions'], 'member@example.com');
        $this->attachExistingRole($member, $team, 'manager');

        $meeting = app(CreateMeeting::class)->execute(
            $team,
            $manager,
            'Annual General Meeting',
            MeetingType::Agm,
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

        app(RecordAttendance::class)->execute(
            $meeting->fresh(),
            $manager,
            $member->id,
            AttendanceStatus::Present,
        );

        app(RecordAttendance::class)->execute(
            $meeting->fresh(),
            $manager,
            $manager->id,
            AttendanceStatus::Present,
        );

        $ballot = app(CreateBallot::class)->execute(
            $team,
            $manager,
            'Approve budget',
            null,
            BallotType::Resolution,
            ElectorateType::AllMembers,
            [
                ['label' => 'Yes'],
                ['label' => 'No'],
            ],
            null,
            50.0,
            now()->subHour(),
            now()->addWeek(),
        );

        app(LinkBallotToMeeting::class)->execute($meeting, $ballot->id, $manager);
        $published = app(PublishBallot::class)->execute($ballot, $manager);

        app(CastVote::class)->execute(
            $published,
            $manager,
            $published->options->firstWhere('label', 'Yes'),
            User::class,
            $manager->id,
        );

        app(CloseBallot::class)->execute($published->fresh(), $manager);

        app(CreateMeetingActionItem::class)->execute(
            $meeting->fresh(),
            $manager,
            'Send revised budget to owners',
            assignedToUserId: $member->id,
        );

        $draft = app(BuildMeetingMinutesDraft::class)->execute($meeting->fresh(), $manager);

        $this->assertStringContainsString('ATTENDANCE SUMMARY', $draft);
        $this->assertStringContainsString('invited members present', $draft);
        $this->assertStringContainsString('QUORUM', $draft);
        $this->assertStringContainsString('Approve budget', $draft);
        $this->assertStringContainsString('RESOLUTIONS', $draft);
        $this->assertStringContainsString('Yes:', $draft);
        $this->assertStringContainsString('Total votes:', $draft);
        $this->assertStringContainsString('ACTION ITEMS', $draft);
        $this->assertStringContainsString('Send revised budget to owners', $draft);
    }

    public function test_section_builder_returns_single_section(): void
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

        $section = app(BuildMeetingMinutesDraft::class)->section(
            $meeting->fresh(),
            'attendance_summary',
            $manager,
        );

        $this->assertNotNull($section);
        $this->assertStringContainsString('ATTENDANCE SUMMARY', $section);
    }

    protected function attachExistingRole($user, $team, string $roleSlug): void
    {
        $roleId = DB::table('roles')->where('slug', $roleSlug)->value('id');

        DB::table('user_role')->insert([
            'user_id' => $user->id,
            'role_id' => $roleId,
            'team_id' => is_object($team) ? $team->id : $team,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
