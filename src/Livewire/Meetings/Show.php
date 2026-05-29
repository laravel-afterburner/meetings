<?php

namespace Afterburner\Meetings\Livewire\Meetings;

use Afterburner\Meetings\Actions\BuildMeetingMinutesDraft;
use Afterburner\Meetings\Actions\RecordAttendance;
use Afterburner\Meetings\Actions\RemoveAttendance;
use Afterburner\Meetings\Actions\UpdateMeeting;
use Afterburner\Meetings\Actions\UpdateMeetingMinutes;
use Afterburner\Meetings\Enums\AttendanceStatus;
use Afterburner\Meetings\Enums\MeetingStatus;
use Afterburner\Meetings\Models\Meeting;
use Afterburner\Meetings\Models\MeetingActionItem;
use Afterburner\Meetings\Support\AttendanceRecorderResolver;
use Afterburner\Meetings\Support\DocumentsIntegration;
use Afterburner\Meetings\Support\MinutesTemplate;
use Afterburner\Meetings\Support\TeamDateTime;
use Afterburner\Meetings\Support\TeamPermissionGate;
use Afterburner\Meetings\Support\VotingIntegration;
use App\Models\Team;
use App\Traits\InteractsWithBanner;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Show extends Component
{
    use InteractsWithBanner;

    public int $teamId;

    public int $meetingId;

    public string $minutes = '';

    public function mount(Team $team, Meeting $meeting): void
    {
        if ($meeting->team_id !== $team->id) {
            abort(404);
        }

        abort_unless(Auth::user()->can('view', $meeting), 403);

        $this->teamId = $team->id;
        $this->meetingId = $meeting->id;
        $this->minutes = $meeting->minutes ?? '';
    }

    public function updateStatus(string $status): void
    {
        $meeting = $this->meeting();

        app(UpdateMeeting::class)->execute(
            $meeting,
            Auth::user(),
            $meeting->title,
            $meeting->type,
            MeetingStatus::from($status),
            $meeting->location,
            $meeting->virtual_link,
            $meeting->agenda_notes,
            $meeting->scheduled_at,
            $meeting->target_role_slugs,
        );

        $this->banner(__('Meeting status updated.'));
    }

    public function recordAttendance(int $userId, string $status): void
    {
        abort_unless(in_array($status, ['present', 'eligible_not_present'], true), 422);

        app(RecordAttendance::class)->execute(
            $this->meeting(),
            Auth::user(),
            $userId,
            AttendanceStatus::from($status),
        );

        $this->banner(__('Attendance recorded.'));
    }

    public function clearAttendance(int $userId): void
    {
        app(RemoveAttendance::class)->execute(
            $this->meeting(),
            Auth::user(),
            $userId,
        );

        $this->banner(__('Attendance cleared.'));
    }

    public function saveMinutes(bool $finalize = false): void
    {
        $this->validate([
            'minutes' => 'nullable|string|max:50000',
        ]);

        app(UpdateMeetingMinutes::class)->execute(
            $this->meeting(),
            Auth::user(),
            filled($this->minutes) ? $this->minutes : null,
            $finalize,
        );

        $this->banner($finalize ? __('Meeting minutes finalized.') : __('Meeting minutes saved.'));
    }

    public function generateMinutesDraft(): void
    {
        abort_unless(Auth::user()->can('recordMinutes', $this->meeting()), 403);
        abort_unless($this->meeting()->minutesAreEditable(), 422);

        $this->minutes = app(BuildMeetingMinutesDraft::class)->execute(
            $this->meeting(),
            Auth::user(),
        );

        $this->banner(__('Minutes draft generated from meeting data.'));
    }

    public function insertMinutesSection(string $section): void
    {
        abort_unless(Auth::user()->can('recordMinutes', $this->meeting()), 403);
        abort_unless($this->meeting()->minutesAreEditable(), 422);

        $text = app(BuildMeetingMinutesDraft::class)->section(
            $this->meeting(),
            $section,
            Auth::user(),
        );

        if (blank($text)) {
            $this->banner(__('No content available for that section.'));

            return;
        }

        $this->minutes = filled($this->minutes)
            ? rtrim($this->minutes)."\n\n".$text
            : $text;
    }

    protected function meeting(): Meeting
    {
        return Meeting::query()
            ->where('team_id', $this->teamId)
            ->with(['creator', 'attendances.recordedBy', 'attendances.user', 'minutesFinalizedBy', 'meetingBallots'])
            ->findOrFail($this->meetingId);
    }

    public function render()
    {
        $team = Team::query()->findOrFail($this->teamId);
        $meeting = $this->meeting();
        $attendanceByUser = $meeting->attendances->keyBy('user_id');
        $invitedUsers = $meeting->invitedUsers();
        $ballotEvents = $meeting->settings['ballot_events'] ?? [];
        $recorder = app(AttendanceRecorderResolver::class)->recorderFor($meeting);

        return view('afterburner-meetings::meetings.livewire.show', [
            'team' => $team,
            'meeting' => $meeting,
            'invitedUsers' => $invitedUsers,
            'attendanceByUser' => $attendanceByUser,
            'canManage' => TeamPermissionGate::allows(Auth::user(), $team->id, 'manage_meetings'),
            'canRecordAttendance' => Auth::user()->can('manageAttendance', $meeting),
            'canRecordMinutes' => Auth::user()->can('recordMinutes', $meeting),
            'canEdit' => Auth::user()->can('update', $meeting),
            'canLinkBallots' => Auth::user()->can('linkBallots', $meeting),
            'attendanceRecorder' => $recorder,
            'documentsEnabled' => DocumentsIntegration::isEnabled(),
            'documentsInstallPrompt' => DocumentsIntegration::shouldPromptInstall(),
            'votingEnabled' => VotingIntegration::isEnabled(),
            'linkedBallots' => VotingIntegration::isEnabled() ? $meeting->linkedBallots() : collect(),
            'ballotEvents' => $ballotEvents,
            'scheduledDisplay' => TeamDateTime::format($team, $meeting->scheduled_at),
            'canViewActionItems' => $this->canViewActionItems($meeting),
            'minutesSections' => app(MinutesTemplate::class)->sections(),
        ]);
    }

    protected function canViewActionItems(Meeting $meeting): bool
    {
        $user = Auth::user();

        if ($user->can('create', [MeetingActionItem::class, $meeting])) {
            return true;
        }

        return MeetingActionItem::query()
            ->where('meeting_id', $meeting->id)
            ->where('team_id', $meeting->team_id)
            ->assignedTo($user->id)
            ->exists();
    }
}
