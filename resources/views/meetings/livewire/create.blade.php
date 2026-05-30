<div>
    <form wire:submit.prevent="saveDraft" class="space-y-6 rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="max-w-xl">
            <x-label for="title" value="Title" />
            <x-input id="title" type="text" class="mt-1 block w-full" wire:model="title" required />
            <x-input-error for="title" class="mt-2" />
        </div>

        <div class="flex flex-wrap gap-4">
            <div class="w-44">
                <x-label for="type" value="Meeting type" />
                <x-select-input id="type" wire:model="type" class="mt-1 block w-full">
                    <option value="agm">AGM</option>
                    <option value="council">Council</option>
                    <option value="special">Special</option>
                </x-select-input>
            </div>

            <div class="w-44">
                <x-label for="status" value="Status" />
                <x-select-input id="status" wire:model="status" class="mt-1 block w-full">
                    <option value="draft">Draft</option>
                    <option value="scheduled">Scheduled</option>
                    <option value="in_progress">In Progress</option>
                    <option value="completed">Completed</option>
                    <option value="cancelled">Cancelled</option>
                </x-select-input>
            </div>
        </div>

        <div class="max-w-xs">
            <x-label for="scheduledAt" value="Scheduled date and time" />
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">({{ $scheduleTimezone }})</p>
            <x-input id="scheduledAt" type="datetime-local" class="mt-1 block w-full" wire:model="scheduledAt" />
            <x-input-error for="scheduledAt" class="mt-2" />
        </div>

        <div class="max-w-xl">
            <x-label for="location" value="Location" />
            <x-input id="location" type="text" class="mt-1 block w-full" wire:model="location" />
        </div>

        <div class="max-w-xl">
            <x-label for="virtualLink" value="Virtual meeting link" />
            <x-input id="virtualLink" type="url" class="mt-1 block w-full" wire:model="virtualLink" placeholder="https://" />
            <x-input-error for="virtualLink" class="mt-2" />
        </div>

        <div>
            <x-label for="agendaNotes" value="Agenda notes" />
            <x-textarea-input id="agendaNotes" wire:model="agendaNotes" rows="5" class="mt-1 block w-full max-w-2xl" />
        </div>

        <div>
            <x-label value="Invite audience" />
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Select which roles receive email and in-app notifications when this meeting is scheduled.</p>
            <div class="mt-3 grid gap-2 sm:grid-cols-2">
                @foreach ($audienceRoles as $role)
                    <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                        <input type="checkbox"
                               wire:model="targetRoleSlugs"
                               value="{{ $role->slug }}"
                               class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900">
                        <span>{{ $role->name }}</span>
                    </label>
                @endforeach
            </div>
            <x-input-error for="targetRoleSlugs" class="mt-2" />
        </div>

        <div class="flex flex-wrap items-center justify-end gap-3">
            <x-secondary-button type="button" wire:click="$redirectRoute('teams.meetings.index', ['team' => $team->id])" no-spinner>
                Cancel
            </x-secondary-button>

            @if ($canDelete)
                <x-danger-button type="button" wire:click="deleteMeeting" wire:confirm="Delete this draft meeting?" no-spinner>
                    Delete
                </x-danger-button>
            @endif

            <x-action-message on="saved" />
            <x-button type="submit" wire:loading.attr="disabled" wire:target="saveDraft">
                {{ $isEditing ? 'Save changes' : 'Save draft' }}
            </x-button>
        </div>
    </form>

    @if ($meetingId)
        <div class="mt-6">
            @livewire('meetings.meeting-agenda-items', [
                'teamId' => $team->id,
                'meetingId' => $meetingId,
            ], key('meeting-agenda-items-create-'.$meetingId))
        </div>
    @else
        <div class="mt-6 rounded-lg border border-dashed border-gray-300 bg-gray-50 p-5 dark:border-gray-600 dark:bg-gray-900/40">
            <p class="text-sm text-gray-600 dark:text-gray-400">
                Save a draft first to build the agenda and link existing records.
            </p>
        </div>
    @endif

    @if ($documentsEnabled)
        <div class="mt-6">
            @if ($meetingId)
                @livewire('meetings.meeting-documents', [
                    'teamId' => $team->id,
                    'meetingId' => $meetingId,
                ], key('meeting-documents-create-'.$meetingId))
            @else
                <div class="rounded-lg border border-dashed border-gray-300 bg-gray-50 p-5 dark:border-gray-600 dark:bg-gray-900/40">
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Save a draft first to attach documents to this meeting.
                    </p>
                </div>
            @endif
        </div>
    @elseif ($documentsInstallPrompt)
        @include('afterburner-meetings::components.documents-install-prompt', ['context' => 'meeting'])
    @endif
</div>
