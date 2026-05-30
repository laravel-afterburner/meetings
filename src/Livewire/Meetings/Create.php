<?php

namespace Afterburner\Meetings\Livewire\Meetings;

use Afterburner\Meetings\Actions\CreateMeeting;
use Afterburner\Meetings\Actions\DeleteMeeting;
use Afterburner\Meetings\Actions\UpdateMeeting;
use Afterburner\Meetings\Enums\MeetingStatus;
use Afterburner\Meetings\Enums\MeetingType;
use Afterburner\Meetings\Models\Meeting;
use Afterburner\Meetings\Support\DocumentsIntegration;
use Afterburner\Meetings\Support\MeetingAudienceService;
use Afterburner\Meetings\Support\TeamDateTime;
use App\Models\Team;
use App\Traits\InteractsWithBanner;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Create extends Component
{
    use InteractsWithBanner;

    public int $teamId;

    public ?int $meetingId = null;

    public string $title = '';

    public string $type = 'agm';

    public string $status = 'draft';

    public ?string $scheduledAt = null;

    public string $location = '';

    public string $virtualLink = '';

    public string $agendaNotes = '';

    /** @var array<int, string> */
    public array $targetRoleSlugs = [];

    public function mount(Team $team, ?int $meetingId = null): void
    {
        if (! Auth::user()->belongsToTeam($team)) {
            abort(403, 'Access denied.');
        }

        $this->teamId = $team->id;

        if ($meetingId) {
            $meeting = Meeting::query()->where('team_id', $team->id)->findOrFail($meetingId);
            abort_unless(Auth::user()->can('update', $meeting), 403);

            $this->meetingId = $meeting->id;
            $this->title = $meeting->title;
            $this->type = $meeting->type->value;
            $this->status = $meeting->status->value;
            $this->scheduledAt = TeamDateTime::toDateTimeLocal($team, $meeting->scheduled_at);
            $this->location = $meeting->location ?? '';
            $this->virtualLink = $meeting->virtual_link ?? '';
            $this->agendaNotes = $meeting->agenda_notes ?? '';
            $this->targetRoleSlugs = $meeting->target_role_slugs ?? [];
        } else {
            $this->targetRoleSlugs = app(MeetingAudienceService::class)
                ->defaultRolesForType($this->type);
        }
    }

    public function updatedType(string $value): void
    {
        if ($this->meetingId !== null) {
            return;
        }

        $this->targetRoleSlugs = app(MeetingAudienceService::class)
            ->defaultRolesForType($value);
    }

    public function saveDraft(): void
    {
        $this->validate([
            'title' => 'required|string|max:255',
            'type' => 'required|in:agm,council,special',
            'status' => 'required|in:draft,scheduled,in_progress,completed,cancelled',
            'scheduledAt' => 'nullable|date',
            'location' => 'nullable|string|max:255',
            'virtualLink' => 'nullable|url|max:2048',
            'agendaNotes' => 'nullable|string|max:5000',
            'targetRoleSlugs' => 'required|array|min:1',
            'targetRoleSlugs.*' => 'string|exists:roles,slug',
        ], [
            'targetRoleSlugs.required' => 'Select at least one audience group.',
        ]);

        $wasNew = $this->meetingId === null;
        $meeting = $this->persistMeeting();
        $team = Team::query()->findOrFail($this->teamId);

        if ($wasNew) {
            $this->meetingId = $meeting->id;
            $this->banner(__('Meeting draft saved. You can build the agenda and attach documents below.'));

            return;
        }

        $this->banner(__('Meeting saved.'));
        $this->redirectRoute('teams.meetings.edit', ['team' => $team, 'meeting' => $meeting]);
    }

    protected function persistMeeting(): Meeting
    {
        $team = Team::query()->findOrFail($this->teamId);
        $scheduledAt = TeamDateTime::fromDateTimeLocal($team, $this->scheduledAt);
        $roles = array_values(array_unique($this->targetRoleSlugs));

        if ($this->meetingId) {
            $meeting = Meeting::query()->where('team_id', $team->id)->findOrFail($this->meetingId);

            return app(UpdateMeeting::class)->execute(
                $meeting,
                Auth::user(),
                $this->title,
                MeetingType::from($this->type),
                MeetingStatus::from($this->status),
                filled($this->location) ? $this->location : null,
                filled($this->virtualLink) ? $this->virtualLink : null,
                filled($this->agendaNotes) ? $this->agendaNotes : null,
                $scheduledAt,
                $roles,
            );
        }

        $meeting = app(CreateMeeting::class)->execute(
            $team,
            Auth::user(),
            $this->title,
            MeetingType::from($this->type),
            filled($this->location) ? $this->location : null,
            filled($this->virtualLink) ? $this->virtualLink : null,
            filled($this->agendaNotes) ? $this->agendaNotes : null,
            $scheduledAt,
            $roles,
        );

        if ($this->status !== MeetingStatus::Draft->value) {
            return app(UpdateMeeting::class)->execute(
                $meeting,
                Auth::user(),
                $this->title,
                MeetingType::from($this->type),
                MeetingStatus::from($this->status),
                filled($this->location) ? $this->location : null,
                filled($this->virtualLink) ? $this->virtualLink : null,
                filled($this->agendaNotes) ? $this->agendaNotes : null,
                $scheduledAt,
                $roles,
            );
        }

        return $meeting;
    }

    public function deleteMeeting(): void
    {
        if (! $this->meetingId) {
            return;
        }

        $team = Team::query()->findOrFail($this->teamId);
        $meeting = Meeting::query()->where('team_id', $team->id)->findOrFail($this->meetingId);

        app(DeleteMeeting::class)->execute($meeting, Auth::user());
        $this->banner(__('Meeting deleted.'));
        $this->redirectRoute('teams.meetings.index', ['team' => $team]);
    }

    public function render()
    {
        $team = Team::query()->findOrFail($this->teamId);

        return view('afterburner-meetings::meetings.livewire.create', [
            'team' => $team,
            'isEditing' => $this->meetingId !== null,
            'canDelete' => $this->meetingId
                && Meeting::query()->whereKey($this->meetingId)->value('status') === MeetingStatus::Draft->value
                && Auth::user()->can('delete', Meeting::query()->find($this->meetingId)),
            'documentsEnabled' => DocumentsIntegration::isEnabled(),
            'documentsInstallPrompt' => DocumentsIntegration::shouldPromptInstall(),
            'scheduleTimezone' => TeamDateTime::teamTimezone($team),
            'audienceRoles' => app(MeetingAudienceService::class)->selectableRoles(),
        ]);
    }
}
