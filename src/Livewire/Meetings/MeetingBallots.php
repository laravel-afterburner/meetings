<?php

namespace Afterburner\Meetings\Livewire\Meetings;

use Afterburner\Meetings\Actions\LinkBallotToMeeting;
use Afterburner\Meetings\Actions\UnlinkBallotFromMeeting;
use Afterburner\Meetings\Models\Meeting;
use Afterburner\Meetings\Support\VotingIntegration;
use Afterburner\Voting\Models\Ballot;
use App\Models\Team;
use App\Traits\InteractsWithBanner;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class MeetingBallots extends Component
{
    use InteractsWithBanner;

    public int $teamId;

    public int $meetingId;

    public bool $showLinkModal = false;

    public string $ballotSearch = '';

    public function mount(int $teamId, int $meetingId): void
    {
        abort_unless(VotingIntegration::isEnabled(), 404);

        $meeting = $this->meeting();

        if ($meeting->team_id !== $teamId) {
            abort(404);
        }

        abort_unless(Auth::user()->can('view', $meeting), 403);

        $this->teamId = $teamId;
        $this->meetingId = $meetingId;
    }

    public function openLinkModal(): void
    {
        abort_unless($this->canLinkBallots(), 403);

        $this->ballotSearch = '';
        $this->showLinkModal = true;
    }

    public function closeLinkModal(): void
    {
        $this->showLinkModal = false;
        $this->ballotSearch = '';
    }

    public function linkBallot(int $ballotId): void
    {
        abort_unless($this->canLinkBallots(), 403);

        try {
            app(LinkBallotToMeeting::class)->execute($this->meeting(), $ballotId, Auth::user());
            $this->banner(__('Ballot linked to meeting.'));
            $this->closeLinkModal();
        } catch (\Throwable $exception) {
            $this->dangerBanner($exception->getMessage());
        }
    }

    public function unlinkBallot(int $ballotId): void
    {
        abort_unless($this->canLinkBallots(), 403);

        try {
            app(UnlinkBallotFromMeeting::class)->execute($this->meeting(), $ballotId, Auth::user());
            $this->banner(__('Ballot unlinked from meeting.'));
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

    protected function canLinkBallots(): bool
    {
        return Auth::user()->can('linkBallots', $this->meeting());
    }

    public function render()
    {
        $meeting = $this->meeting()->load('meetingBallots');
        $team = Team::query()->findOrFail($this->teamId);
        $linkedBallotIds = $meeting->meetingBallots->pluck('ballot_id');
        $linkedBallots = $meeting->linkedBallots();
        $ballotEvents = $meeting->settings['ballot_events'] ?? [];

        $availableBallots = Ballot::query()
            ->forTeam($this->teamId)
            ->when($linkedBallotIds->isNotEmpty(), fn ($query) => $query->whereNotIn('id', $linkedBallotIds))
            ->when(filled($this->ballotSearch), function ($query) {
                $term = '%'.$this->ballotSearch.'%';
                $query->where('title', 'like', $term);
            })
            ->orderByDesc('created_at')
            ->limit(25)
            ->get();

        return view('afterburner-meetings::meetings.livewire.meeting-ballots', [
            'team' => $team,
            'meeting' => $meeting,
            'linkedBallots' => $linkedBallots,
            'availableBallots' => $availableBallots,
            'canLinkBallots' => $this->canLinkBallots(),
            'ballotEvents' => $ballotEvents,
        ]);
    }
}
