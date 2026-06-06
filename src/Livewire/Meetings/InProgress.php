<?php

namespace Afterburner\Meetings\Livewire\Meetings;

use Afterburner\Meetings\Actions\CompleteMeeting;
use Afterburner\Meetings\Enums\MeetingStatus;
use Afterburner\Meetings\Models\Meeting;
use Afterburner\Meetings\Models\MeetingActionItem;
use App\Support\TeamDateTime;
use App\Models\Team;
use App\Traits\InteractsWithBanner;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class InProgress extends Component
{
    use InteractsWithBanner;

    public int $teamId;

    public int $meetingId;

    public bool $showFinishConfirmModal = false;

    public function mount(Team $team, Meeting $meeting): void
    {
        if ($meeting->team_id !== $team->id) {
            abort(404);
        }

        abort_unless(Auth::user()->can('conductSession', $meeting) || Auth::user()->can('complete', $meeting), 403);

        if ($meeting->status !== MeetingStatus::InProgress) {
            if ($meeting->status === MeetingStatus::Scheduled) {
                $this->redirectRoute('teams.meetings.show', ['team' => $team, 'meeting' => $meeting]);

                return;
            }

            if ($meeting->status === MeetingStatus::Completed) {
                $this->redirectRoute('teams.meetings.completed', ['team' => $team, 'meeting' => $meeting]);

                return;
            }

            abort(404);
        }

        $this->teamId = $team->id;
        $this->meetingId = $meeting->id;
    }

    public function requestFinishMeeting(): void
    {
        abort_unless(Auth::user()->can('complete', $this->meeting()), 403);

        $this->showFinishConfirmModal = true;
    }

    public function cancelFinishMeeting(): void
    {
        $this->showFinishConfirmModal = false;
    }

    public function finishMeeting(): void
    {
        abort_unless(Auth::user()->can('complete', $this->meeting()), 403);

        $this->showFinishConfirmModal = false;

        try {
            app(CompleteMeeting::class)->execute($this->meeting(), Auth::user());

            $this->redirectRoute('teams.meetings.completed', [
                'team' => $this->teamId,
                'meeting' => $this->meetingId,
            ]);
        } catch (\Throwable $exception) {
            $this->dangerBanner($exception->getMessage());
        }
    }

    protected function meeting(): Meeting
    {
        return Meeting::query()
            ->where('team_id', $this->teamId)
            ->findOrFail($this->meetingId);
    }

    protected function hasUnfinalizedMinutes(Meeting $meeting): bool
    {
        return $meeting->minutes_finalized_at === null && filled($meeting->minutes);
    }

    public function render()
    {
        $team = Team::query()->findOrFail($this->teamId);
        $meeting = $this->meeting();

        return view('afterburner-meetings::meetings.livewire.in-progress', [
            'team' => $team,
            'meeting' => $meeting,
            'scheduledDisplay' => TeamDateTime::formatDisplay($team, $meeting->scheduled_at),
            'canManageActionItems' => Auth::user()->can('create', [MeetingActionItem::class, $meeting]),
            'canRecordMinutes' => Auth::user()->can('recordMinutes', $meeting),
            'canFinishMeeting' => Auth::user()->can('complete', $meeting),
            'hasUnfinalizedMinutes' => $this->hasUnfinalizedMinutes($meeting),
        ]);
    }
}
