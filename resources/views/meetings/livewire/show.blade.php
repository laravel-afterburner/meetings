<div>
    <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm sm:p-6 dark:border-gray-700 dark:bg-gray-800">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ $meeting->type->label() }} · {{ $meeting->status->label() }}</p>
                @if ($scheduledDisplay)
                    <p class="mt-2 text-sm text-gray-700 dark:text-gray-300">{!! $scheduledDisplay !!}</p>
                @endif
                @if ($meeting->location)
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ $meeting->location }}</p>
                @endif
                @if ($meeting->virtual_link)
                    <a href="{{ $meeting->virtual_link }}" target="_blank" rel="noopener noreferrer"
                       class="mt-1 inline-block text-sm text-indigo-600 hover:text-indigo-500 dark:text-indigo-400">
                        Join virtual meeting
                    </a>
                @endif
            </div>

            <div class="flex flex-wrap items-center gap-2">
                @if ($canStartMeeting)
                    <x-button type="button" wire:click="openRollCall" no-spinner>
                        Start meeting
                    </x-button>
                @endif
                @if ($canContinueMeeting)
                    <x-afterburner-meetings::meeting-nav-icon
                        action="continue"
                        :href="route('teams.meetings.in-progress', ['team' => $team, 'meeting' => $meeting])"
                        title="Continue meeting"
                        wire:navigate
                    />
                @endif
                @if ($canOpenWrapUp)
                    <x-afterburner-meetings::meeting-nav-icon
                        action="wrap-up"
                        :href="route('teams.meetings.completed', ['team' => $team, 'meeting' => $meeting])"
                        title="Wrap up meeting"
                        wire:navigate
                    />
                @endif
            </div>
        </div>

        @if ($meetingInProgress)
            <div class="mt-4 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 dark:border-amber-800 dark:bg-amber-950/30">
                <p class="text-sm text-amber-900 dark:text-amber-200">This meeting is currently in progress.</p>
            </div>
        @endif

        @if ($meeting->agenda_notes)
            <div class="mt-6 border-t border-gray-200 pt-6 dark:border-gray-700">
                <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Agenda notes</h4>
                <p class="mt-2 whitespace-pre-wrap text-sm text-gray-600 dark:text-gray-400">{{ $meeting->agenda_notes }}</p>
            </div>
        @endif
    </div>

    @if ($hasAgendaItems)
        <div class="mt-8">
            @include('afterburner-meetings::meetings.partials.show.agenda-items', [
                'team' => $team,
                'agendaItems' => $agendaItems,
            ])
        </div>
    @endif

    @if ($votingEnabled && $hasLinkedBallots)
        <div class="mt-8">
            @include('afterburner-meetings::meetings.partials.show.linked-ballots', [
                'team' => $team,
                'linkedBallots' => $linkedBallots,
                'ballotEvents' => $ballotEvents,
            ])
        </div>
    @endif

    @if ($documentsEnabled && $hasLinkedDocuments)
        <div class="mt-8">
            @include('afterburner-meetings::meetings.partials.show.documents', [
                'team' => $team,
                'linkedDocuments' => $linkedDocuments,
            ])
        </div>
    @endif

    @if ($hasAttendance)
        <div class="mt-8 rounded-lg border border-gray-200 bg-white p-4 shadow-sm sm:p-6 dark:border-gray-700 dark:bg-gray-800">
            <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Attendance</h4>
            <div class="mt-4 space-y-3">
                @foreach ($invitedUsers as $invitee)
                    @php
                        $attendance = $attendanceByUser->get($invitee->id);
                    @endphp
                    @if ($attendance)
                        <div class="flex items-center justify-between gap-3 rounded-md border border-gray-200 px-4 py-3 dark:border-gray-600">
                            <p class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $invitee->name }}</p>
                            <span class="text-xs text-gray-500 dark:text-gray-400">{{ $attendance->status->label() }}</span>
                        </div>
                    @endif
                @endforeach
            </div>
        </div>
    @endif

    @if ($hasActionItems)
        <div class="mt-8">
            @include('afterburner-meetings::meetings.partials.show.action-items', [
                'team' => $team,
                'actionItems' => $actionItems,
            ])
        </div>
    @endif

    @if ($showMinutes)
        <div class="mt-8 rounded-lg border border-gray-200 bg-white p-4 shadow-sm sm:p-6 dark:border-gray-700 dark:bg-gray-800">
            <div class="flex flex-wrap items-center gap-2">
                <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Meeting minutes</h4>
                <x-afterburner-meetings::meeting-minutes-status-badge :meeting="$meeting" />
            </div>
            @if ($meeting->minutes_finalized_at)
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    Finalized {!! \Afterburner\Meetings\Support\TeamDateTime::formatDisplay($team, $meeting->minutes_finalized_at) !!}
                    @if ($meeting->minutesFinalizedBy)
                        by {{ $meeting->minutesFinalizedBy->name }}
                    @endif
                </p>
            @endif
            <p class="mt-4 whitespace-pre-wrap text-sm text-gray-600 dark:text-gray-400">{{ $meeting->minutes }}</p>
        </div>
    @endif

    <x-dialog-modal wire:model.live="showRollCallModal">
        <x-slot name="title">Attendance roll call</x-slot>
        <x-slot name="content">
            <p class="text-sm text-gray-600 dark:text-gray-400">
                Record who is present before the meeting begins.
            </p>
            <div class="mt-4 space-y-3">
                @foreach ($invitedUsers as $invitee)
                    <div class="flex flex-wrap items-center justify-between gap-3 rounded-md border border-gray-200 px-4 py-3 dark:border-gray-600">
                        <p class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $invitee->name }}</p>
                        <div class="flex flex-wrap gap-2">
                            @foreach (['present' => 'Present', 'eligible_not_present' => 'Not present'] as $value => $label)
                                <button type="button"
                                        wire:click="$set('rollCallAttendance.{{ $invitee->id }}', '{{ $value }}')"
                                        class="rounded-md px-2 py-1 text-xs font-medium {{ ($rollCallAttendance[$invitee->id] ?? null) === $value ? 'bg-gray-800 text-white dark:bg-gray-200 dark:text-gray-900' : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600' }}">
                                    {{ $label }}
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </x-slot>
        <x-slot name="footer">
            <x-secondary-button wire:click="closeRollCall">Cancel</x-secondary-button>
            <x-button class="ms-3" wire:click="saveRollCallAndStart" wire:loading.attr="disabled" wire:target="saveRollCallAndStart" no-spinner>
                Save &amp; start meeting
            </x-button>
        </x-slot>
    </x-dialog-modal>
</div>
