<div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
    <div class="rounded-md bg-gray-50 px-4 py-3 text-sm text-gray-700 dark:bg-gray-900/40 dark:text-gray-300">
        <p class="font-medium text-gray-900 dark:text-gray-100">Attendance summary</p>
        <p class="mt-1">{{ $attendanceSummary['present'] }} of {{ $attendanceSummary['total_invited'] }} invited present</p>
        @if ($attendanceRecorder)
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Recorder: {{ $attendanceRecorder->name }}</p>
        @endif
    </div>

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
                    @else
                        <p class="text-xs text-gray-500 dark:text-gray-400">Not recorded</p>
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
                                    class="rounded p-1 text-gray-400 hover:text-red-600 dark:hover:text-red-400"
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
