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
use Afterburner\Meetings\Support\VotingIntegration;
use App\Models\Team;
use App\Traits\InteractsWithBanner;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Create extends Component
{
    use InteractsWithBanner;

    public int $teamId;

    public ?int $meetingId = null;

    public bool $openedAsEdit = false;

    public bool $detailsOnly = false;

    public string $title = '';

    public string $type = 'agm';

    public ?string $scheduledAt = null;

    public string $location = '';

    public ?string $virtualLink = null;

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

            if ($meeting->status === MeetingStatus::InProgress) {
                $this->redirectRoute('teams.meetings.in-progress', ['team' => $team, 'meeting' => $meeting]);

                return;
            }

            if ($meeting->status === MeetingStatus::Cancelled) {
                $this->redirectRoute('teams.meetings.show', ['team' => $team, 'meeting' => $meeting]);

                return;
            }

            $this->detailsOnly = $meeting->status === MeetingStatus::Completed;
            $this->openedAsEdit = true;
            $this->meetingId = $meeting->id;
            $this->title = $meeting->title;
            $this->type = $meeting->type->value;
            $this->scheduledAt = TeamDateTime::toDateTimeLocal($team, $meeting->scheduled_at);
            $this->location = $meeting->location ?? '';
            $this->virtualLink = $meeting->virtual_link;
            $this->agendaNotes = $meeting->agenda_notes ?? '';
            $this->targetRoleSlugs = $meeting->target_role_slugs ?? [];
        } else {
            abort_unless(Auth::user()->can('create', [Meeting::class, $team]), 403);

            $this->targetRoleSlugs = app(MeetingAudienceService::class)
                ->defaultRolesForType($this->type);
        }
    }

    public function updatedType(string $value): void
    {
        if ($this->meetingId !== null || $this->detailsOnly) {
            return;
        }

        if ($this->targetRoleSlugs !== []) {
            return;
        }

        $this->targetRoleSlugs = app(MeetingAudienceService::class)
            ->defaultRolesForType($value);
    }

    protected function prepareForValidation($attributes): array
    {
        if (($attributes['virtualLink'] ?? null) === '') {
            $attributes['virtualLink'] = null;
            $this->virtualLink = null;
        }

        return $attributes;
    }

    public function saveDraft(): void
    {
        $this->validateMeetingDetails();

        $wasNew = $this->meetingId === null;
        $meeting = $this->persistMeeting();

        if ($wasNew) {
            $this->banner(__('Meeting draft saved. Build the agenda and attach documents below.'));
            $this->redirectRoute('teams.meetings.edit', [
                'team' => $this->teamId,
                'meeting' => $meeting->id,
            ]);

            return;
        }

        $this->banner(__('Meeting saved.'));
    }

    public function scheduleMeeting(): void
    {
        abort_if($this->detailsOnly, 403);

        $this->validateMeetingDetails();

        $meeting = $this->persistMeeting(MeetingStatus::Scheduled);

        $this->redirectAfterSave($meeting, __('Meeting scheduled. Invited members will be notified.'));
    }

    public function deleteMeeting(): void
    {
        if (! $this->meetingId || $this->detailsOnly) {
            return;
        }

        $team = Team::query()->findOrFail($this->teamId);
        $meeting = Meeting::query()->where('team_id', $team->id)->findOrFail($this->meetingId);

        app(DeleteMeeting::class)->execute($meeting, Auth::user());
        $this->banner(__('Meeting deleted.'));
        $this->redirectRoute('teams.meetings.index', ['team' => $team]);
    }

    protected function validateMeetingDetails(): void
    {
        $this->validate([
            'title' => 'required|string|max:255',
            'type' => 'required|in:agm,council,special',
            'scheduledAt' => 'nullable|date',
            'location' => 'nullable|string|max:255',
            'virtualLink' => 'nullable|url|max:2048',
            'agendaNotes' => 'nullable|string|max:5000',
            'targetRoleSlugs' => 'required|array|min:1',
            'targetRoleSlugs.*' => 'string|exists:roles,slug',
        ], [
            'targetRoleSlugs.required' => 'Select at least one audience group.',
        ]);
    }

    protected function persistMeeting(?MeetingStatus $status = null): Meeting
    {
        $team = Team::query()->findOrFail($this->teamId);
        $scheduledAt = TeamDateTime::fromDateTimeLocal($team, $this->scheduledAt);
        $roles = array_values(array_unique($this->targetRoleSlugs));

        if ($this->meetingId) {
            $meeting = Meeting::query()->where('team_id', $team->id)->findOrFail($this->meetingId);
            $targetStatus = $status ?? $meeting->status;

            return app(UpdateMeeting::class)->execute(
                $meeting,
                Auth::user(),
                $this->title,
                MeetingType::from($this->type),
                $targetStatus,
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

        if ($status === MeetingStatus::Scheduled) {
            return app(UpdateMeeting::class)->execute(
                $meeting,
                Auth::user(),
                $this->title,
                MeetingType::from($this->type),
                MeetingStatus::Scheduled,
                filled($this->location) ? $this->location : null,
                filled($this->virtualLink) ? $this->virtualLink : null,
                filled($this->agendaNotes) ? $this->agendaNotes : null,
                $scheduledAt,
                $roles,
            );
        }

        return $meeting;
    }

    protected function redirectAfterSave(Meeting $meeting, string $message): void
    {
        $this->banner(__($message));

        if ($this->detailsOnly) {
            $this->redirectRoute('teams.meetings.completed', [
                'team' => $this->teamId,
                'meeting' => $meeting->id,
            ]);

            return;
        }

        $this->redirectRoute('teams.meetings.show', [
            'team' => $this->teamId,
            'meeting' => $meeting->id,
        ]);
    }

    public function render()
    {
        $team = Team::query()->findOrFail($this->teamId);
        $meeting = $this->meetingId
            ? Meeting::query()->where('team_id', $team->id)->find($this->meetingId)
            : null;

        return view('afterburner-meetings::meetings.livewire.create', [
            'team' => $team,
            'meeting' => $meeting,
            'isEditing' => $this->meetingId !== null,
            'canDelete' => $this->meetingId
                && ! $this->detailsOnly
                && Meeting::query()->whereKey($this->meetingId)->value('status') === MeetingStatus::Draft->value
                && Auth::user()->can('delete', Meeting::query()->find($this->meetingId)),
            'canSchedule' => ! $this->detailsOnly
                && ($meeting === null || $meeting->status === MeetingStatus::Draft),
            'documentsEnabled' => DocumentsIntegration::isEnabled() && ! $this->detailsOnly,
            'documentsInstallPrompt' => DocumentsIntegration::shouldPromptInstall() && ! $this->detailsOnly,
            'votingEnabled' => VotingIntegration::isEnabled() && ! $this->detailsOnly,
            'scheduleTimezone' => TeamDateTime::datetimeLocalTimezone($team),
            'scheduleTeamTimezoneHint' => TeamDateTime::datetimeLocalTeamTimezoneHint($team),
            'audienceRoles' => app(MeetingAudienceService::class)->selectableRoles(),
        ]);
    }
}
