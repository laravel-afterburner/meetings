<?php

namespace Afterburner\Meetings\Livewire\Meetings;

use Afterburner\Meetings\Actions\BuildMeetingMinutesDraft;
use Afterburner\Meetings\Actions\UpdateMeetingMinutes;
use Afterburner\Meetings\Models\Meeting;
use Afterburner\Meetings\Support\MinutesTemplate;
use App\Models\Team;
use App\Traits\InteractsWithBanner;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class MeetingMinutes extends Component
{
    use InteractsWithBanner;

    public int $teamId;

    public int $meetingId;

    public string $minutes = '';

    public function mount(int $teamId, int $meetingId): void
    {
        $meeting = $this->meeting();

        if ($meeting->team_id !== $teamId) {
            abort(404);
        }

        abort_unless(Auth::user()->can('view', $meeting), 403);

        $this->teamId = $teamId;
        $this->meetingId = $meetingId;
        $this->minutes = $meeting->minutes ?? '';
    }

    public function saveMinutes(bool $finalize = false): void
    {
        abort_unless($this->canRecordMinutes(), 403);

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
        abort_unless($this->canRecordMinutes(), 403);
        abort_unless($this->meeting()->minutesAreEditable(), 422);

        $this->minutes = app(BuildMeetingMinutesDraft::class)->execute(
            $this->meeting(),
            Auth::user(),
        );

        $this->banner(__('Minutes draft generated from meeting data.'));
    }

    public function insertMinutesSection(string $section): void
    {
        abort_unless($this->canRecordMinutes(), 403);
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
            ->with('minutesFinalizedBy')
            ->findOrFail($this->meetingId);
    }

    protected function canRecordMinutes(): bool
    {
        return Auth::user()->can('recordMinutes', $this->meeting());
    }

    public function render()
    {
        $meeting = $this->meeting();
        $team = Team::query()->findOrFail($this->teamId);

        return view('afterburner-meetings::meetings.livewire.meeting-minutes', [
            'team' => $team,
            'meeting' => $meeting,
            'canRecordMinutes' => $this->canRecordMinutes(),
            'minutesSections' => app(MinutesTemplate::class)->sections(),
        ]);
    }
}
