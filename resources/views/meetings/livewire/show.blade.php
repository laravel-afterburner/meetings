<div>
    <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ $meeting->type->label() }} · {{ $meeting->status->label() }}</p>
                @if ($scheduledDisplay)
                    <p class="mt-2 text-sm text-gray-700 dark:text-gray-300">{{ $scheduledDisplay }}</p>
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
                @if ($meeting->target_role_slugs)
                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                        Invited roles: {{ implode(', ', $meeting->target_role_slugs) }}
                    </p>
                @endif
            </div>

            <div class="flex flex-wrap gap-2">
                @if ($canEdit)
                    <x-secondary-button type="button" wire:click="$redirectRoute('teams.meetings.edit', ['team' => $team->id, 'meeting' => $meeting->id])">
                        Edit
                    </x-secondary-button>
                @endif
                @if ($canManage && $meeting->status === \Afterburner\Meetings\Enums\MeetingStatus::Scheduled)
                    <x-button type="button" wire:click="updateStatus('in_progress')" no-spinner>
                        Start meeting
                    </x-button>
                @endif
                @if ($canManage && $meeting->status === \Afterburner\Meetings\Enums\MeetingStatus::InProgress)
                    <x-button type="button" wire:click="updateStatus('completed')" no-spinner>
                        Complete meeting
                    </x-button>
                @endif
            </div>
        </div>

        @if ($meeting->agenda_notes)
            <div class="mt-6 border-t border-gray-200 pt-6 dark:border-gray-700">
                <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Agenda notes</h4>
                <p class="mt-2 whitespace-pre-wrap text-sm text-gray-600 dark:text-gray-400">{{ $meeting->agenda_notes }}</p>
            </div>
        @endif
    </div>

    @if ($votingEnabled)
        <div class="mt-8">
            @livewire('meetings.meeting-ballots', ['teamId' => $team->id, 'meetingId' => $meeting->id], key('meeting-ballots-'.$meeting->id))
        </div>
    @endif

    @isset($ballotSummaries)
        @if ($ballotSummaries->isNotEmpty())
            <div class="mt-8 rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Linked ballot status</h4>
                <div class="mt-4 space-y-4">
                    @foreach ($ballotSummaries as $summary)
                        <div class="rounded-md border border-gray-200 px-4 py-3 dark:border-gray-600">
                            <p class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $summary['ballot']->title }}</p>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $summary['status_label'] }}</p>
                            @if (isset($summary['quorum']))
                                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                                    Quorum: {{ $summary['quorum']['cast'] }} of {{ $summary['quorum']['eligible'] }} eligible
                                    @if ($summary['quorum']['met'])
                                        — met
                                    @endif
                                </p>
                                <p class="text-sm text-gray-600 dark:text-gray-400">{{ $summary['votes_cast'] }} votes cast</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    @endisset

    @if ($documentsEnabled)
        <div class="mt-8">
            @livewire('meetings.meeting-documents', ['teamId' => $team->id, 'meetingId' => $meeting->id], key('meeting-documents-show-'.$meeting->id))
        </div>
    @endif

    <div class="mt-8 rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Attendance roll call</h4>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Record who is present from the invited list.
                    @if ($attendanceRecorder)
                        Attendance recorder: {{ $attendanceRecorder->name }}.
                    @endif
                </p>
            </div>
        </div>

        @isset($attendanceSummary)
            <div class="mt-4 rounded-md bg-gray-50 px-4 py-3 text-sm text-gray-700 dark:bg-gray-900/40 dark:text-gray-300">
                <p class="font-medium text-gray-900 dark:text-gray-100">Meeting attendance summary</p>
                <p class="mt-1">{{ $attendanceSummary['present'] }} of {{ $attendanceSummary['total_invited'] }} invited present</p>
            </div>
        @endisset

        <div class="mt-4 space-y-3">
            @foreach ($invitedUsers as $invitee)
                @php
                    $attendance = $attendanceByUser->get($invitee->id);
                @endphp
                <div class="flex flex-wrap items-center justify-between gap-3 rounded-md border border-gray-200 px-4 py-3 dark:border-gray-600">
                    <div>
                        <p class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $invitee->name }}</p>
                        @if ($attendance)
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $attendance->status->label() }}</p>
                        @endif
                    </div>

                    @if ($canRecordAttendance)
                        <div class="flex flex-wrap items-center gap-2">
                            @foreach (['present' => 'Present', 'eligible_not_present' => 'Not present'] as $value => $label)
                                <button type="button"
                                        wire:click="recordAttendance({{ $invitee->id }}, '{{ $value }}')"
                                        class="rounded-md px-2 py-1 text-xs font-medium {{ $attendance?->status->value === $value ? 'bg-gray-800 text-white dark:bg-gray-200 dark:text-gray-900' : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600' }}">
                                    {{ $label }}
                                </button>
                            @endforeach
                            @if ($attendance)
                                <button type="button"
                                        wire:click="clearAttendance({{ $invitee->id }})"
                                        class="p-1 text-gray-400 hover:text-red-600 dark:hover:text-red-400 rounded"
                                        title="Clear attendance">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                    </svg>
                                </button>
                            @endif
                        </div>
                    @elseif ($attendance)
                        <span class="text-xs text-gray-500 dark:text-gray-400">{{ $attendance->status->label() }}</span>
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    <div class="mt-8 rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Meeting minutes</h4>
                @if ($meeting->minutes_finalized_at)
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Finalized {{ $meeting->minutes_finalized_at->timezone(\Afterburner\Meetings\Support\TeamDateTime::teamTimezone($team))->format('M j, Y g:i A') }}
                        @if ($meeting->minutesFinalizedBy)
                            by {{ $meeting->minutesFinalizedBy->name }}
                        @endif
                    </p>
                @endif
            </div>
        </div>

        @if ($canRecordMinutes && $meeting->minutesAreEditable())
            <textarea wire:model="minutes" rows="8"
                      class="mt-4 block w-full rounded-md border-gray-300 shadow-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100"></textarea>
            <div class="mt-4 flex flex-wrap justify-end gap-3">
                <x-secondary-button type="button" wire:click="saveMinutes(false)" no-spinner>
                    Save minutes
                </x-secondary-button>
                <x-button type="button" wire:click="saveMinutes(true)" wire:confirm="Finalize these minutes? They cannot be edited afterward." no-spinner>
                    Finalize minutes
                </x-button>
            </div>
        @elseif ($meeting->minutes)
            <p class="mt-4 whitespace-pre-wrap text-sm text-gray-600 dark:text-gray-400">{{ $meeting->minutes }}</p>
        @else
            <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">No minutes recorded yet.</p>
        @endif
    </div>
</div>
