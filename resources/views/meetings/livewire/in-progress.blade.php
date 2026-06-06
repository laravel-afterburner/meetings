<div class="space-y-8">
    <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 shadow-sm sm:p-6 dark:border-amber-800 dark:bg-amber-950/30">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-sm font-medium text-amber-900 dark:text-amber-200">Meeting in progress</p>
                <h3 class="mt-1 text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $meeting->title }}</h3>
                @if ($scheduledDisplay)
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{!! $scheduledDisplay !!}</p>
                @endif
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <x-afterburner-meetings::meeting-nav-icon
                    action="summary"
                    :href="route('teams.meetings.show', ['team' => $team, 'meeting' => $meeting])"
                    title="View meeting summary"
                    wire:navigate
                />
                @if ($canFinishMeeting)
                    <x-button type="button"
                              wire:click="requestFinishMeeting"
                              wire:loading.attr="disabled"
                              wire:target="finishMeeting,requestFinishMeeting"
                              no-spinner>
                        Finish meeting
                    </x-button>
                @endif
            </div>
        </div>
    </div>

    @if ($canRecordMinutes)
        <x-afterburner-meetings::settings-section
            title="Minutes"
            description="Record minutes while the meeting is underway. Finalize when the official record is ready."
        >
            @livewire('meetings.meeting-minutes', [
                'teamId' => $team->id,
                'meetingId' => $meeting->id,
            ], key('meeting-minutes-in-progress-'.$meeting->id))
        </x-afterburner-meetings::settings-section>
    @endif

    @if ($canManageActionItems)
        <x-afterburner-meetings::settings-section
            title="Action items"
            description="Assign follow-up tasks to people at the meeting or in council positions. Notifications are sent when the meeting is finished."
        >
            @livewire('meetings.meeting-action-items', [
                'teamId' => $team->id,
                'meetingId' => $meeting->id,
                'assigneeScope' => 'meeting',
            ], key('meeting-action-items-in-progress-'.$meeting->id))
        </x-afterburner-meetings::settings-section>
    @endif

    @if ($canFinishMeeting)
        <x-confirmation-modal wire:model.live="showFinishConfirmModal">
            <x-slot name="title">
                Finish meeting?
            </x-slot>

            <x-slot name="content">
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    This ends the live session and notifies assigned members of their action items.
                </p>
                @if ($hasUnfinalizedMinutes)
                    <div class="mt-4 rounded-md border border-amber-200 bg-amber-50 p-4 dark:border-amber-800 dark:bg-amber-950/30">
                        <p class="text-sm text-amber-900 dark:text-amber-200">
                            Minutes are saved as a draft but not finalized. You can finalize them now or finish up on the wrap-up page after the meeting ends.
                        </p>
                    </div>
                @endif
            </x-slot>

            <x-slot name="footer">
                <x-secondary-button wire:click="cancelFinishMeeting">
                    Cancel
                </x-secondary-button>
                <x-button type="button"
                          wire:click="finishMeeting"
                          wire:loading.attr="disabled"
                          wire:target="finishMeeting"
                          class="ms-3"
                          no-spinner>
                    Finish meeting
                </x-button>
            </x-slot>
        </x-confirmation-modal>
    @endif
</div>
