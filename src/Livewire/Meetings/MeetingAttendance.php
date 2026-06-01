<?php

namespace Afterburner\Meetings\Livewire\Meetings;

use Afterburner\Meetings\Actions\RecordAttendance;
use Afterburner\Meetings\Actions\RemoveAttendance;
use Afterburner\Meetings\Enums\AttendanceStatus;
use Afterburner\Meetings\Models\Meeting;
use Afterburner\Meetings\Support\AttendanceRecorderResolver;
use App\Models\Team;
use App\Traits\InteractsWithBanner;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class MeetingAttendance extends Component
{
    use InteractsWithBanner;

    public int $teamId;

    public int $meetingId;

    public function mount(int $teamId, int $meetingId): void
    {
        $meeting = $this->meeting();

        if ($meeting->team_id !== $teamId) {
            abort(404);
        }

        abort_unless(Auth::user()->can('view', $meeting), 403);

        $this->teamId = $teamId;
        $this->meetingId = $meetingId;
    }

    public function recordAttendance(int $userId, string $status): void
    {
        abort_unless($this->canRecordAttendance(), 403);
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
        abort_unless($this->canRecordAttendance(), 403);

        app(RemoveAttendance::class)->execute(
            $this->meeting(),
            Auth::user(),
            $userId,
        );

        $this->banner(__('Attendance cleared.'));
    }

    protected function meeting(): Meeting
    {
        return Meeting::query()
            ->where('team_id', $this->teamId)
            ->with(['attendances.user'])
            ->findOrFail($this->meetingId);
    }

    protected function canRecordAttendance(): bool
    {
        return Auth::user()->can('manageAttendance', $this->meeting());
    }

    public function render()
    {
        $meeting = $this->meeting();
        $team = Team::query()->findOrFail($this->teamId);
        $invitedUsers = $meeting->invitedUsers();
        $attendanceByUser = $meeting->attendances->keyBy('user_id');
        $recorder = app(AttendanceRecorderResolver::class)->recorderFor($meeting);
        $presentCount = $meeting->attendances
            ->filter(fn ($attendance) => $attendance->status->value === 'present')
            ->count();

        return view('afterburner-meetings::meetings.livewire.meeting-attendance', [
            'team' => $team,
            'meeting' => $meeting,
            'invitedUsers' => $invitedUsers,
            'attendanceByUser' => $attendanceByUser,
            'canRecordAttendance' => $this->canRecordAttendance(),
            'attendanceRecorder' => $recorder,
            'attendanceSummary' => [
                'present' => $presentCount,
                'total_invited' => $invitedUsers->count(),
            ],
        ]);
    }
}
