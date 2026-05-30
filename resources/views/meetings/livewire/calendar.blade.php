<div>
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div class="flex items-center gap-2">
            <x-secondary-button type="button" wire:click="previousMonth" no-spinner aria-label="Previous month">
                &larr;
            </x-secondary-button>
            <x-secondary-button type="button" wire:click="goToToday" no-spinner>
                Today
            </x-secondary-button>
            <x-secondary-button type="button" wire:click="nextMonth" no-spinner aria-label="Next month">
                &rarr;
            </x-secondary-button>
            <h2 class="ml-2 text-lg font-semibold text-gray-900 dark:text-gray-100">
                {{ $monthLabel }}
            </h2>
            <span class="text-sm text-gray-500 dark:text-gray-400">({{ $timezone }})</span>
        </div>

        @if ($canCreate)
            <x-button type="button" wire:click="openCreateForDate('{{ $todayDate }}')" no-spinner>
                Add event
            </x-button>
        @endif
    </div>

    <div
        class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800"
        x-data="{
            selecting: false,
            anchor: null,
            hover: null,
            canCreate: @js($canCreate),
            startSelect(date) {
                if (! this.canCreate) return;
                this.selecting = true;
                this.anchor = date;
                this.hover = date;
            },
            extendSelect(date) {
                if (! this.selecting) return;
                this.hover = date;
            },
            finishSelect() {
                if (! this.selecting || ! this.anchor || ! this.hover) {
                    this.selecting = false;
                    return;
                }
                const start = this.anchor <= this.hover ? this.anchor : this.hover;
                const end = this.anchor <= this.hover ? this.hover : this.anchor;
                $wire.openCreateForRange(start, end);
                this.selecting = false;
                this.anchor = null;
                this.hover = null;
            },
            isSelected(date) {
                if (! this.selecting || ! this.anchor || ! this.hover) return false;
                const start = this.anchor <= this.hover ? this.anchor : this.hover;
                const end = this.anchor <= this.hover ? this.hover : this.anchor;
                return date >= start && date <= end;
            }
        }"
        x-on:mouseup.window="finishSelect()"
    >
        <div class="grid grid-cols-7 border-b border-gray-200 bg-gray-50 text-xs font-medium uppercase tracking-wider text-gray-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-400">
            @foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $weekday)
                <div class="px-3 py-2 text-center">{{ $weekday }}</div>
            @endforeach
        </div>

        @foreach ($weeks as $weekIndex => $week)
            @php
                $laneCount = max($week['laneCount'], 1);
                $barAreaHeight = $week['laneCount'] > 0 ? ($week['laneCount'] * 1.375 + 0.25).'rem' : '0.25rem';
            @endphp
            <div wire:key="calendar-week-{{ $weekIndex }}" class="relative grid grid-cols-7 border-b border-gray-200 last:border-b-0 dark:border-gray-700">
                @foreach ($week['days'] as $day)
                    @php
                        $dayCellClasses = 'min-h-[9rem] border-r border-gray-200 p-2 last:border-r-0 dark:border-gray-700';
                        if (! $day['inMonth']) {
                            $dayCellClasses .= ' bg-gray-50/70 dark:bg-gray-900/40';
                        }
                    @endphp
                    <div
                        wire:key="calendar-day-{{ $day['date'] }}"
                        class="{{ $dayCellClasses }}"
                        :class="{ 'bg-indigo-50 dark:bg-indigo-950/30': isSelected('{{ $day['date'] }}') }"
                        x-on:mousedown.prevent="startSelect('{{ $day['date'] }}')"
                        x-on:mouseenter="extendSelect('{{ $day['date'] }}')"
                    >
                        @if ($canCreate)
                            <button
                                type="button"
                                @class([
                                    'relative z-10 mb-1 inline-flex h-7 w-7 items-center justify-center rounded-full text-sm',
                                    'bg-indigo-600 text-white' => $day['isToday'],
                                    'text-gray-900 dark:text-gray-100' => ! $day['isToday'] && $day['inMonth'],
                                    'text-gray-400 dark:text-gray-500' => ! $day['inMonth'],
                                ])
                                wire:click.stop="openCreateForDate('{{ $day['date'] }}')"
                            >
                                {{ $day['day'] }}
                            </button>
                        @else
                            <button
                                type="button"
                                @class([
                                    'relative z-10 mb-1 inline-flex h-7 w-7 items-center justify-center rounded-full text-sm',
                                    'bg-indigo-600 text-white' => $day['isToday'],
                                    'text-gray-900 dark:text-gray-100' => ! $day['isToday'] && $day['inMonth'],
                                    'text-gray-400 dark:text-gray-500' => ! $day['inMonth'],
                                ])
                            >
                                {{ $day['day'] }}
                            </button>
                        @endif

                        <div aria-hidden="true" style="height: {{ $barAreaHeight }};"></div>

                        @php
                            $timedRowHeight = 1.375;
                            $timedAreaHeight = $day['timedLaneCount'] > 0
                                ? ($day['timedLaneCount'] * $timedRowHeight).'rem'
                                : '0';
                        @endphp
                        <div
                            class="relative z-10"
                            style="min-height: {{ $timedAreaHeight }};"
                        >
                            @foreach ($day['timedEntries'] as $timed)
                                @php
                                    $entry = $timed['entry'];
                                    $columnWidth = 100 / $timed['columnCount'];
                                    $columnLeft = $columnWidth * $timed['column'];
                                    $isMeeting = $entry->kind === 'meeting';
                                    $chipClasses = $isMeeting
                                        ? 'bg-blue-100 text-blue-900 hover:bg-blue-200 dark:bg-blue-900/50 dark:text-blue-100 dark:hover:bg-blue-900/70'
                                        : 'bg-emerald-100 text-emerald-900 hover:bg-emerald-200 dark:bg-emerald-900/50 dark:text-emerald-100 dark:hover:bg-emerald-900/70';
                                    $clickAction = $isMeeting
                                        ? 'viewMeeting('.str_replace('meeting-', '', $entry->id).')'
                                        : 'openEditEvent('.(int) str_replace('event-', '', $entry->id).')';
                                @endphp
                                <button
                                    type="button"
                                    wire:key="calendar-timed-{{ $day['date'] }}-{{ $entry->id }}-{{ $timed['lane'] }}-{{ $timed['column'] }}"
                                    wire:click.stop="{{ $clickAction }}"
                                    class="absolute truncate rounded px-1 py-0.5 text-left text-[11px] leading-4 {{ $chipClasses }}"
                                    style="top: {{ $timed['lane'] * $timedRowHeight }}rem; left: calc({{ $columnLeft }}% + 1px); width: calc({{ $columnWidth }}% - 2px);"
                                    title="{{ $timed['timeRangeLabel'] }} · {{ $entry->title }}"
                                >
                                    <span class="font-semibold">{{ $timed['timeLabel'] }}</span>
                                    <span class="ml-1">{{ $entry->title }}</span>
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endforeach

                @if ($week['laneCount'] > 0)
                    <div
                        class="pointer-events-none absolute inset-x-0 top-10 z-20 grid grid-cols-7 gap-x-0 px-2"
                        style="height: {{ $barAreaHeight }};"
                    >
                        @foreach ($week['bars'] as $bar)
                            @php
                                $entry = $bar['entry'];
                                $isMeeting = $entry->kind === 'meeting';
                                $barClasses = $isMeeting
                                    ? 'bg-blue-100 text-blue-900 hover:bg-blue-200 dark:bg-blue-900/50 dark:text-blue-100 dark:hover:bg-blue-900/70'
                                    : 'bg-emerald-100 text-emerald-900 hover:bg-emerald-200 dark:bg-emerald-900/50 dark:text-emerald-100 dark:hover:bg-emerald-900/70';
                                $roundedClasses = trim(
                                    ($bar['segmentStart'] ? 'rounded-l-md' : 'rounded-l-none')
                                    .' '
                                    .($bar['segmentEnd'] ? 'rounded-r-md' : 'rounded-r-none')
                                );
                                $barClick = $isMeeting
                                    ? 'viewMeeting('.str_replace('meeting-', '', $entry->id).')'
                                    : 'openEditEvent('.(int) str_replace('event-', '', $entry->id).')';
                                if (! $bar['segmentEnd']) {
                                    $barClasses .= $isMeeting
                                        ? ' border-r border-blue-200 dark:border-blue-800/60'
                                        : ' border-r border-emerald-200 dark:border-emerald-800/60';
                                }
                            @endphp
                            <button
                                type="button"
                                wire:key="calendar-bar-{{ $weekIndex }}-{{ $entry->id }}-{{ $bar['startCol'] }}"
                                wire:click.stop="{{ $barClick }}"
                                class="pointer-events-auto h-5 truncate px-1.5 text-left text-[11px] font-medium leading-5 shadow-sm {{ $barClasses }} {{ $roundedClasses }}"
                                style="grid-column: {{ $bar['startCol'] + 1 }} / {{ $bar['endCol'] + 2 }}; grid-row: {{ $bar['lane'] + 1 }};"
                                title="{{ $entry->title }}"
                            >
                                @if ($bar['showLabel'])
                                    {{ $entry->title }}
                                @else
                                    <span class="sr-only">{{ $entry->title }}</span>
                                @endif
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>
        @endforeach
    </div>

    <div class="mt-4 flex flex-wrap items-center gap-4 text-xs text-gray-600 dark:text-gray-400">
        <span class="inline-flex items-center gap-2">
            <span class="h-3 w-3 rounded bg-blue-100 dark:bg-blue-900/50"></span>
            Meetings
        </span>
        <span class="inline-flex items-center gap-2">
            <span class="h-3 w-3 rounded bg-emerald-100 dark:bg-emerald-900/50"></span>
            Calendar events
        </span>
        @if ($canCreate)
            <span>Click a day to add an event, or click and drag across days for multi-day events.</span>
        @endif
    </div>

    <div class="mt-8 rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Subscribe in Apple Calendar or Google Calendar</h3>
        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
            Use this private link to follow your team calendar. Meetings and calendar events sync automatically when apps refresh the subscription.
        </p>

        <div class="mt-4 flex flex-wrap gap-3">
            <a href="{{ $webcalUrl }}" class="inline-flex items-center rounded-md bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700 dark:bg-gray-100 dark:text-gray-900 dark:hover:bg-white">
                Subscribe on Mac
            </a>
            <x-secondary-button type="button" wire:click="copyFeedUrl" no-spinner>
                Copy feed URL
            </x-secondary-button>
        </div>

        <p class="mt-3 break-all text-xs text-gray-500 dark:text-gray-400">{{ $feedUrl }}</p>
        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
            In Google Calendar: Settings &rarr; Add calendar &rarr; From URL, then paste the feed URL above.
        </p>
    </div>

    @if ($showEventModal)
        <div class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0" wire:keydown.escape.window="closeEventModal">
            <div class="fixed inset-0 bg-gray-500/75 dark:bg-gray-900/80" wire:click="closeEventModal"></div>

            <div class="relative mx-auto max-w-lg rounded-lg bg-white p-6 shadow-xl dark:bg-gray-800">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                    {{ $editingEventId ? 'Edit event' : 'New event' }}
                </h3>

                <form wire:submit.prevent="saveEvent" class="mt-4 space-y-4">
                    <div>
                        <x-label for="eventTitle" value="Title" />
                        <x-input id="eventTitle" type="text" class="mt-1 block w-full" wire:model="title" required />
                        <x-input-error for="title" class="mt-2" />
                    </div>

                    <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                        <input type="checkbox" wire:model.live="allDay" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900">
                        All-day event
                    </label>

                    @if ($allDay)
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <x-label for="startDate" value="Start date" />
                                <x-input id="startDate" type="date" class="mt-1 block w-full" wire:model.live="startDate" required />
                                <x-input-error for="startDate" class="mt-2" />
                            </div>
                            <div>
                                <x-label for="endDate" value="End date" />
                                <x-input id="endDate" type="date" class="mt-1 block w-full" wire:model="endDate" min="{{ $startDate }}" required />
                                <x-input-error for="endDate" class="mt-2" />
                            </div>
                        </div>
                    @else
                        <div>
                            <x-label for="startsAtLocal" value="Starts" />
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">({{ $timezone }})</p>
                            <x-input id="startsAtLocal" type="datetime-local" class="mt-1 block w-full" wire:model.live="startsAtLocal" required />
                            <x-input-error for="startsAtLocal" class="mt-2" />
                        </div>

                        <div>
                            <x-label for="endsAtLocal" value="Ends" />
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">({{ $timezone }})</p>
                            <x-input id="endsAtLocal" type="datetime-local" class="mt-1 block w-full" wire:model="endsAtLocal" min="{{ $startsAtLocal }}" required />
                            <x-input-error for="endsAtLocal" class="mt-2" />
                        </div>
                    @endif

                    <div>
                        <x-label for="eventLocation" value="Location" />
                        <x-input id="eventLocation" type="text" class="mt-1 block w-full" wire:model="location" />
                    </div>

                    <div>
                        <x-label for="eventDescription" value="Notes" />
                        <x-textarea-input id="eventDescription" wire:model="description" rows="3" class="mt-1 block w-full" />
                    </div>

                    <div class="flex flex-wrap items-center justify-end gap-3">
                        @if ($editingEventId)
                            <x-danger-button type="button" wire:click="deleteEvent" wire:confirm="Delete this event?" no-spinner>
                                Delete
                            </x-danger-button>
                        @endif
                        <x-secondary-button type="button" wire:click="closeEventModal" no-spinner>
                            Cancel
                        </x-secondary-button>
                        <x-button type="submit" no-spinner>
                            Save
                        </x-button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
