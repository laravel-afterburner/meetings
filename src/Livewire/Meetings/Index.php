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

    public function mount(Team $team): void
    {
        $user = Auth::user();

        if (! $user->belongsToTeam($team)) {
            abort(403, 'Access denied.');
        }

        abort_unless(
            \Afterburner\Meetings\Support\MeetingsPermissions::canViewSection($user, $team, \Afterburner\Meetings\Support\MeetingsPermissions::SECTION_MEETINGS),
            403
        );

        $this->teamId = $team->id;
    }

    public function render()
    {
        $team = Team::query()->findOrFail($this->teamId);
        $user = Auth::user();

        $meetings = Meeting::query()
            ->forTeam($this->teamId)
            ->with('creator')
            ->withCount(['actionItems as overdue_action_items_count' => fn ($actionItems) => $actionItems->overdue()])
            ->orderByDesc('scheduled_at')
            ->orderByDesc('created_at')
            ->paginate(15);

        return view('afterburner-meetings::meetings.livewire.index', [
            'team' => $team,
            'meetings' => $meetings,
            'canCreate' => $user->can('create', [Meeting::class, $team]),
        ]);
    }
}
