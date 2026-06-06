<div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm sm:p-6 dark:border-gray-700 dark:bg-gray-800">
    <div class="flex flex-wrap items-center gap-2">
        @if ($meeting->minutes_finalized_at)
            <p class="text-xs text-gray-500 dark:text-gray-400">
                Finalized {!! \App\Support\TeamDateTime::formatDisplay($team, $meeting->minutes_finalized_at) !!}
                @if ($meeting->minutesFinalizedBy)
                    by {{ $meeting->minutesFinalizedBy->name }}
                @endif
            </p>
        @else
            <x-afterburner-meetings::meeting-minutes-status-badge :meeting="$meeting" />
        @endif
    </div>

    @if ($canRecordMinutes && $meeting->minutesAreEditable())
        <div class="mt-4 flex flex-wrap gap-2">
            <x-secondary-button type="button" wire:click="generateMinutesDraft" no-spinner>
                Generate draft from meeting data
            </x-secondary-button>
            @foreach ($minutesSections as $sectionKey => $section)
                <x-secondary-button type="button" wire:click="insertMinutesSection('{{ $sectionKey }}')" no-spinner>
                    Insert {{ $section['label'] }}
                </x-secondary-button>
            @endforeach
        </div>
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
    @elseif (filled($meeting->minutes))
        <p class="mt-4 whitespace-pre-wrap text-sm text-gray-600 dark:text-gray-400">{{ $meeting->minutes }}</p>
    @else
        <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">No minutes recorded yet.</p>
    @endif
</div>
