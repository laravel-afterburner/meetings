<?php

namespace Afterburner\Meetings\Livewire\Meetings;

use Afterburner\Meetings\Actions\StartMeeting;
use Afterburner\Meetings\Enums\MeetingStatus;
use Afterburner\Meetings\Models\Meeting;
use Afterburner\Meetings\Models\MeetingActionItem;
use Afterburner\Meetings\Support\DocumentsIntegration;
use App\Support\TeamDateTime;
use Afterburner\Meetings\Support\VotingIntegration;
use App\Models\Team;
use App\Traits\InteractsWithBanner;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Show extends Component
{
    use InteractsWithBanner;

    public int $teamId;

    public int $meetingId;

    public bool $showRollCallModal = false;

    /** @var array<int, string> */
    public array $rollCallAttendance = [];

    public function mount(Team $team, Meeting $meeting): void
    {
        if ($meeting->team_id !== $team->id) {
            abort(404);
        }

        abort_unless(Auth::user()->can('view', $meeting), 403);

        if ($meeting->status === MeetingStatus::InProgress
            && (Auth::user()->can('conductSession', $meeting) || Auth::user()->can('complete', $meeting))) {
            $this->redirectRoute('teams.meetings.in-progress', [
                'team' => $team,
                'meeting' => $meeting,
            ]);

            return;
        }

        $this->teamId = $team->id;
        $this->meetingId = $meeting->id;
    }

    public function openRollCall(): void
    {
        abort_unless(Auth::user()->can('start', $this->meeting()), 403);

        $this->rollCallAttendance = [];
        $this->showRollCallModal = true;
    }

    public function closeRollCall(): void
    {
        $this->showRollCallModal = false;
        $this->rollCallAttendance = [];
    }

    public function saveRollCallAndStart(): void
    {
        abort_unless(Auth::user()->can('start', $this->meeting()), 403);

        try {
            app(StartMeeting::class)->execute(
                $this->meeting(),
                Auth::user(),
                $this->rollCallAttendance,
            );

            $this->redirectRoute('teams.meetings.in-progress', [
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
            ->with([
                'attendances.user',
                'minutesFinalizedBy',
                'meetingBallots',
                'agendaItems.reference',
                ...DocumentsIntegration::meetingEagerLoads(),
                'actionItems.assignee',
            ])
            ->findOrFail($this->meetingId);
    }

    public function render()
    {
        $team = Team::query()->findOrFail($this->teamId);
        $meeting = $this->meeting();
        $attendanceByUser = $meeting->attendances->keyBy('user_id');
        $invitedUsers = $meeting->invitedUsers();
        $linkedBallots = VotingIntegration::isEnabled() ? $meeting->linkedBallots() : collect();
        $linkedDocuments = DocumentsIntegration::linkedDocumentsFor($meeting);
        $actionItems = $this->visibleActionItems($meeting);

        return view('afterburner-meetings::meetings.livewire.show', [
            'team' => $team,
            'meeting' => $meeting,
            'invitedUsers' => $invitedUsers,
            'attendanceByUser' => $attendanceByUser,
            'documentsEnabled' => DocumentsIntegration::isEnabled(),
            'votingEnabled' => VotingIntegration::isEnabled(),
            'agendaItems' => $meeting->agendaItems,
            'linkedBallots' => $linkedBallots,
            'linkedDocuments' => $linkedDocuments,
            'actionItems' => $actionItems,
            'ballotEvents' => $meeting->settings['ballot_events'] ?? [],
            'hasAgendaItems' => $meeting->agendaItems->isNotEmpty(),
            'hasLinkedBallots' => $linkedBallots->isNotEmpty(),
            'hasLinkedDocuments' => DocumentsIntegration::isEnabled() && $linkedDocuments->isNotEmpty(),
            'hasActionItems' => $actionItems->isNotEmpty(),
            'hasAttendance' => $meeting->attendances->isNotEmpty(),
            'showMinutes' => filled($meeting->minutes),
            'scheduledDisplay' => TeamDateTime::formatDisplay($team, $meeting->scheduled_at),
            'canStartMeeting' => Auth::user()->can('start', $meeting),
            'canOpenWrapUp' => $meeting->status === MeetingStatus::Completed
                && Auth::user()->can('reviseAfterCompletion', $meeting),
            'canContinueMeeting' => $meeting->status === MeetingStatus::InProgress
                && (Auth::user()->can('conductSession', $meeting) || Auth::user()->can('complete', $meeting)),
            'meetingInProgress' => $meeting->status === MeetingStatus::InProgress,
        ]);
    }

    /**
     * @return Collection<int, MeetingActionItem>
     */
    protected function visibleActionItems(Meeting $meeting): Collection
    {
        $user = Auth::user();

        $query = MeetingActionItem::query()
            ->with(['assignee', 'creator'])
            ->where('meeting_id', $meeting->id)
            ->where('team_id', $meeting->team_id)
            ->orderBy('sort_order')
            ->orderBy('id');

        if (! $user->can('create', [MeetingActionItem::class, $meeting])) {
            $query->assignedTo($user->id);
        }

        return $query->get();
    }
}
