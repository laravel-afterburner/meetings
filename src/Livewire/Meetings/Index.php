<?php

namespace Afterburner\Meetings\Livewire\Meetings;

use Afterburner\Meetings\Enums\MeetingStatus;
use Afterburner\Meetings\Models\Meeting;
use App\Models\Team;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public int $teamId;

    public string $tab = 'upcoming';

    protected $queryString = [
        'tab' => ['except' => 'upcoming'],
    ];

    public function mount(Team $team): void
    {
        if (! Auth::user()->belongsToTeam($team)) {
            abort(403, 'Access denied.');
        }

        abort_unless(Auth::user()->can('viewAny', Meeting::class), 403);

        $this->teamId = $team->id;
    }

    public function setTab(string $tab): void
    {
        if (! in_array($tab, ['upcoming', 'past', 'draft'], true)) {
            return;
        }

        $this->tab = $tab;
        $this->resetPage();
    }

    public function createMeeting()
    {
        return $this->redirectRoute('teams.meetings.create', ['team' => $this->teamId]);
    }

    public function render()
    {
        $team = Team::query()->findOrFail($this->teamId);
        $query = Meeting::query()->forTeam($this->teamId)->with('creator');

        $query = match ($this->tab) {
            'upcoming' => $query->whereIn('status', [MeetingStatus::Scheduled, MeetingStatus::InProgress]),
            'past' => $query->whereIn('status', [MeetingStatus::Completed, MeetingStatus::Cancelled]),
            'draft' => $query->where('status', MeetingStatus::Draft),
            default => $query,
        };

        $meetings = $query
            ->withCount(['actionItems as overdue_action_items_count' => fn ($actionItems) => $actionItems->overdue()])
            ->orderByDesc('scheduled_at')
            ->orderByDesc('created_at')
            ->paginate(15);

        return view('afterburner-meetings::meetings.livewire.index', [
            'team' => $team,
            'meetings' => $meetings,
            'canCreate' => Auth::user()->can('create', [Meeting::class, $team]),
        ]);
    }
}
