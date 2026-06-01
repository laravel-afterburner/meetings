<div class="divide-y divide-gray-200 dark:divide-gray-700">
    <x-afterburner-meetings::settings-section
        title="Meeting details"
        :description="$detailsOnly ? 'Update basic meeting information after the meeting has concluded.' : 'Title, schedule, location, and who is invited to this meeting.'"
    >
        <form wire:submit.prevent="saveDraft" class="space-y-6 rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            @if ($isEditing && $meeting)
                <div class="flex flex-wrap items-center gap-2">
                    <span @class([
                        'inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium',
                        $meeting->status->badgeClasses(),
                    ])>
                        {{ $meeting->status->label() }}
                    </span>
                </div>
            @endif

            <div>
                <x-label for="title" value="Title" />
                <x-input id="title" type="text" class="mt-1 block w-full" wire:model="title" required />
                <x-input-error for="title" class="mt-2" />
            </div>

            <div class="flex flex-wrap items-end gap-4">
                <div class="w-44">
                    <x-label for="type" value="Meeting type" />
                    <x-select-input id="type" wire:model.live="type" class="mt-1 block w-full" :disabled="$detailsOnly">
                        <option value="agm">Annual General</option>
                        <option value="council">Council</option>
                        <option value="special">Special</option>
                    </x-select-input>
                </div>

                <div class="min-w-[12rem] flex-1">
                    <x-label for="scheduledAt">Scheduled for ({{ $scheduleTimezone }})</x-label>
                    <x-input id="scheduledAt" type="datetime-local" class="mt-1 block w-full" wire:model="scheduledAt" />
                    <x-input-error for="scheduledAt" class="mt-2" />
                </div>
            </div>

            <div>
                <x-label for="location" value="Location" />
                <x-input id="location" type="text" class="mt-1 block w-full" wire:model="location" />
            </div>

            <div>
                <x-label for="virtualLink" value="Virtual meeting link" />
                <x-input id="virtualLink" type="url" class="mt-1 block w-full" wire:model="virtualLink" placeholder="https://" />
                <x-input-error for="virtualLink" class="mt-2" />
            </div>

            <div>
                <x-label for="agendaNotes" value="Agenda notes" />
                <x-textarea-input id="agendaNotes" wire:model="agendaNotes" rows="5" class="mt-1 block w-full" />
            </div>

            @unless ($detailsOnly)
                <div>
                    <x-label value="Invite audience" />
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Select which roles receive email and in-app notifications when this meeting is scheduled.</p>
                    <div class="mt-3 grid gap-2 sm:grid-cols-2">
                        @foreach ($audienceRoles as $role)
                            <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                <input type="checkbox"
                                       wire:model.live="targetRoleSlugs"
                                       value="{{ $role->slug }}"
                                       class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900">
                                <span>{{ $role->name }}</span>
                            </label>
                        @endforeach
                    </div>
                    <x-input-error for="targetRoleSlugs" class="mt-2" />
                </div>
            @endunless

            <div class="flex flex-wrap items-center justify-end gap-3 border-t border-gray-200 pt-6 dark:border-gray-700">
                @if ($detailsOnly && $meetingId)
                    <x-afterburner-meetings::meeting-nav-icon
                        action="wrap-up"
                        :href="route('teams.meetings.completed', ['team' => $team, 'meeting' => $meetingId])"
                        title="Back to wrap up"
                        wire:navigate
                    />
                @else
                    <x-afterburner-meetings::meeting-nav-icon
                        action="summary"
                        :href="$openedAsEdit && $meetingId ? route('teams.meetings.show', ['team' => $team, 'meeting' => $meetingId]) : route('teams.meetings.index', ['team' => $team])"
                        :title="$openedAsEdit ? 'View meeting' : 'Back to meetings'"
                        wire:navigate
                    />
                @endif

                @if ($canDelete)
                    <x-danger-button type="button" wire:click="deleteMeeting" wire:confirm="Delete this draft meeting?" no-spinner>
                        Delete
                    </x-danger-button>
                @endif

                <x-action-message on="saved" />
                <x-secondary-button type="submit" wire:loading.attr="disabled" wire:target="saveDraft">
                    {{ $isEditing ? 'Save changes' : 'Save draft' }}
                </x-secondary-button>
                @if ($canSchedule)
                    <x-button type="button"
                              wire:click="scheduleMeeting"
                              wire:loading.attr="disabled"
                              wire:target="scheduleMeeting"
                              no-spinner>
                        Schedule meeting
                    </x-button>
                @endif
            </div>
        </form>
    </x-afterburner-meetings::settings-section>

    @if ($isEditing && ! $detailsOnly)
        <x-afterburner-meetings::settings-section
            title="Agenda"
            description="Build the agenda manually or link existing records."
        >
            @livewire('meetings.meeting-agenda-items', [
                'teamId' => $team->id,
                'meetingId' => $meetingId,
                'readOnly' => false,
                'embedded' => true,
            ], key('meeting-agenda-items-create-'.$meetingId))
        </x-afterburner-meetings::settings-section>

        @if ($votingEnabled)
            <x-afterburner-meetings::settings-section
                title="Linked ballots"
                description="Reference ballots discussed or voted on during this meeting."
            >
                @livewire('meetings.meeting-ballots', [
                    'teamId' => $team->id,
                    'meetingId' => $meetingId,
                    'readOnly' => false,
                    'embedded' => true,
                ], key('meeting-ballots-edit-'.$meetingId))
            </x-afterburner-meetings::settings-section>
        @endif

        @if ($documentsEnabled)
            <x-afterburner-meetings::settings-section
                title="Documents"
                description="Attach agendas, notices, and supporting materials."
            >
                @livewire('meetings.meeting-documents', [
                    'teamId' => $team->id,
                    'meetingId' => $meetingId,
                    'readOnly' => false,
                    'embedded' => true,
                ], key('meeting-documents-create-'.$meetingId))
            </x-afterburner-meetings::settings-section>
        @elseif ($documentsInstallPrompt)
            <x-afterburner-meetings::settings-section
                title="Documents"
                description="Attach agendas, notices, and supporting materials."
            >
                @include('afterburner-meetings::components.documents-install-prompt', ['context' => 'meeting'])
            </x-afterburner-meetings::settings-section>
        @endif
    @endif
</div>
