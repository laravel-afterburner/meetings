<div class="space-y-8">
    <div class="rounded-lg border border-green-200 bg-green-50 p-4 shadow-sm sm:p-6 dark:border-green-800 dark:bg-green-950/30">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-sm font-medium text-green-900 dark:text-green-200">Wrap up meeting</p>
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
                @if ($canEditMeeting)
                    <x-afterburner-meetings::meeting-nav-icon
                        action="edit"
                        :href="route('teams.meetings.edit', ['team' => $team, 'meeting' => $meeting])"
                        title="Edit meeting details"
                        wire:navigate
                    />
                @endif
                @if ($canCompilePackage && $documentsEnabled && $packagePdfAvailable)
                    <x-afterburner-meetings::meeting-nav-icon
                        action="export"
                        wire:click="requestCompilePackage"
                        wire:loading.attr="disabled"
                        wire:target="requestCompilePackage,compilePackage,confirmCompilePackage"
                        title="Save meeting package to documents"
                    />
                @endif
            </div>
        </div>
    </div>

    @if ($canRecordMinutes || filled($meeting->minutes))
        <x-afterburner-meetings::settings-section
            title="Minutes"
            description="Finalize the official meeting record before saving the document package."
        >
            @if ($canRecordMinutes)
                @livewire('meetings.meeting-minutes', [
                    'teamId' => $team->id,
                    'meetingId' => $meeting->id,
                ], key('meeting-minutes-completed-'.$meeting->id))
            @else
                <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm sm:p-6 dark:border-gray-700 dark:bg-gray-800">
                    <div class="flex flex-wrap items-center gap-2">
                        <x-afterburner-meetings::meeting-minutes-status-badge :meeting="$meeting" />
                    </div>
                    @if ($meeting->minutes_finalized_at)
                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                            Finalized {!! \Afterburner\Meetings\Support\TeamDateTime::formatDisplay($team, $meeting->minutes_finalized_at) !!}
                            @if ($meeting->minutesFinalizedBy)
                                by {{ $meeting->minutesFinalizedBy->name }}
                            @endif
                        </p>
                    @endif
                    <p class="mt-4 whitespace-pre-wrap text-sm text-gray-600 dark:text-gray-400">{{ $meeting->minutes }}</p>
                </div>
            @endif
        </x-afterburner-meetings::settings-section>
    @endif

    @if ($canRecordAttendance)
        <x-afterburner-meetings::settings-section
            title="Attendance"
            description="Update who was present after the meeting has concluded."
        >
            @livewire('meetings.meeting-attendance', [
                'teamId' => $team->id,
                'meetingId' => $meeting->id,
            ], key('meeting-attendance-completed-'.$meeting->id))
        </x-afterburner-meetings::settings-section>
    @endif

    @if ($canManageActionItems)
        <x-afterburner-meetings::settings-section
            title="Action items"
            description="Assign or reassign follow-up tasks. Changes notify members immediately."
        >
            @livewire('meetings.meeting-action-items', [
                'teamId' => $team->id,
                'meetingId' => $meeting->id,
                'assigneeScope' => 'meeting',
                'embedded' => true,
            ], key('meeting-action-items-completed-'.$meeting->id))
        </x-afterburner-meetings::settings-section>
    @endif

    @if ($canCompilePackage && $documentsEnabled && $packagePdfAvailable)
        <x-confirmation-modal wire:model.live="showCompileConfirmModal">
            <x-slot name="title">
                Save meeting package to documents?
            </x-slot>

            <x-slot name="content">
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    This creates a single PDF with meeting details, attendance, agenda, minutes, ballots, action items, and linked documents,
                    then saves it to the {{ config('afterburner-meetings.documents_package.folder_name', 'Meetings') }} folder in your document library.
                </p>
                @if (count($compileWarnings) > 0)
                    <div class="mt-4 rounded-md border border-amber-200 bg-amber-50 p-4 dark:border-amber-800 dark:bg-amber-950/30">
                        <p class="text-sm font-medium text-amber-900 dark:text-amber-200">Outstanding items</p>
                        <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-amber-800 dark:text-amber-300">
                            @foreach ($compileWarnings as $warning)
                                <li>{{ $warning }}</li>
                            @endforeach
                        </ul>
                        @if ($hasUnfinalizedMinutes)
                            <p class="mt-3 text-sm text-amber-800 dark:text-amber-300">
                                Finalize minutes in the section above before compiling, or continue now and the package will be marked as draft minutes.
                            </p>
                        @elseif ($hasOpenItems)
                            <p class="mt-3 text-sm text-amber-800 dark:text-amber-300">
                                You may want to finish follow-up work before compiling, or continue now and update the package later.
                            </p>
                        @endif
                    </div>
                @endif
            </x-slot>

            <x-slot name="footer">
                <x-secondary-button wire:click="cancelCompilePackage">
                    Cancel
                </x-secondary-button>
                <x-button type="button"
                          wire:click="confirmCompilePackage"
                          wire:loading.attr="disabled"
                          wire:target="confirmCompilePackage,compilePackage"
                          class="ms-3"
                          no-spinner>
                    Save package
                </x-button>
            </x-slot>
        </x-confirmation-modal>
    @elseif ($canCompilePackage && $documentsEnabled && ! $packagePdfAvailable)
        <div class="rounded-lg border border-dashed border-gray-300 bg-gray-50 p-5 dark:border-gray-600 dark:bg-gray-900/40">
            <p class="text-sm text-gray-600 dark:text-gray-400">
                Install barryvdh/laravel-dompdf in the host application to save meeting packages as PDFs.
            </p>
        </div>
    @endif
</div>
