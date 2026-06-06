<div>
    <div class="mb-6 space-y-4" wire:key="calendar-toolbar-{{ $month }}">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div
                class="flex shrink-0 items-center gap-2"
                x-data="{ cancelSelect() { $dispatch('calendar-cancel-select') } }"
            >
                <x-secondary-button type="button" wire:click="previousMonth" x-on:mousedown="cancelSelect()" no-spinner aria-label="Previous month">
                    &larr;
                </x-secondary-button>
                <x-secondary-button type="button" wire:click="goToToday" x-on:mousedown="cancelSelect()" no-spinner>
                    Today
                </x-secondary-button>
                <x-secondary-button type="button" wire:click="nextMonth" x-on:mousedown="cancelSelect()" no-spinner aria-label="Next month">
                    &rarr;
                </x-secondary-button>
            </div>

            @if ($canCreate)
                <x-button type="button" wire:click="openCreateForDate('{{ $todayDate }}')" no-spinner class="shrink-0">
                    Add event
                </x-button>
            @endif
        </div>

        <div class="flex min-w-0 flex-wrap items-center gap-x-3 gap-y-2">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                {{ $monthLabel }}
            </h2>
            <span class="text-sm text-gray-500 dark:text-gray-400">({{ $timezone }})</span>

            @if ($canChooseDisplayTimezone)
                <div class="inline-flex rounded-md border border-gray-200 p-0.5 dark:border-gray-600" role="group" aria-label="Calendar time zone">
                    <button
                        type="button"
                        wire:click="setDisplayTimezoneMode('user')"
                        @class([
                            'rounded px-2 py-1 text-xs font-medium transition',
                            $displayTimezoneMode === 'user'
                                ? 'bg-gray-900 text-white dark:bg-gray-100 dark:text-gray-900'
                                : 'text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100',
                        ])
                    >
                        My time
                    </button>
                    <button
                        type="button"
                        wire:click="setDisplayTimezoneMode('team')"
                        @class([
                            'rounded px-2 py-1 text-xs font-medium transition',
                            $displayTimezoneMode === 'team'
                                ? 'bg-gray-900 text-white dark:bg-gray-100 dark:text-gray-900'
                                : 'text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100',
                        ])
                    >
                        Team time
                    </button>
                </div>
            @endif
        </div>
    </div>

    <div
        wire:key="calendar-grid-{{ $month }}"
        class="overflow-x-auto rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800"
        x-data="{
            selecting: false,
            anchor: null,
            hover: null,
            canCreate: @js($canCreate),
            cancelSelect() {
                this.selecting = false;
                this.anchor = null;
                this.hover = null;
            },
            startSelect(date, event) {
                if (! this.canCreate) return;
                if (event.target.closest('button, a')) return;
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
                    this.cancelSelect();
                    return;
                }
                const start = this.anchor <= this.hover ? this.anchor : this.hover;
                const end = this.anchor <= this.hover ? this.hover : this.anchor;
                $wire.openCreateForRange(start, end);
                this.cancelSelect();
            },
            isSelected(date) {
                if (! this.selecting || ! this.anchor || ! this.hover) return false;
                const start = this.anchor <= this.hover ? this.anchor : this.hover;
                const end = this.anchor <= this.hover ? this.hover : this.anchor;
                return date >= start && date <= end;
            }
        }"
        x-on:calendar-cancel-select.window="cancelSelect()"
        x-on:mouseup.window="finishSelect()"
        x-on:calendar-scroll-to-month-start.window="$nextTick(() => document.getElementById('calendar-month-anchor')?.scrollIntoView({ block: 'center', behavior: 'smooth' }))"
    >
        <div class="grid min-w-[36rem] grid-cols-7 border-b border-gray-200 bg-gray-50 text-[10px] font-medium uppercase tracking-wider text-gray-500 sm:min-w-[42rem] sm:text-xs dark:border-gray-700 dark:bg-gray-900 dark:text-gray-400">
            @foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $weekday)
                <div class="px-1 py-2 text-center sm:px-3">{{ $weekday }}</div>
            @endforeach
        </div>

        @foreach ($weeks as $weekIndex => $week)
            @php
                $weekKey = $week['days'][0]['date'];
                $laneCount = max($week['laneCount'], 1);
                $barAreaHeight = $week['laneCount'] > 0 ? ($week['laneCount'] * 1.375 + 0.25).'rem' : '0.25rem';
            @endphp
            <div wire:key="calendar-week-{{ $weekKey }}" class="relative grid min-w-[36rem] grid-cols-7 border-b border-gray-200 last:border-b-0 sm:min-w-[42rem] dark:border-gray-700">
                @foreach ($week['days'] as $day)
                    @php
                        $dayCellClasses = 'min-h-[5.5rem] border-r border-gray-200 p-1 last:border-r-0 sm:min-h-[7rem] sm:p-2 lg:min-h-[9rem] dark:border-gray-700';
                        if (! $day['inMonth']) {
                            $dayCellClasses .= ' bg-gray-50/70 dark:bg-gray-900/40';
                        }
                    @endphp
                    <div
                        wire:key="calendar-day-{{ $day['date'] }}"
                        @if ($day['date'] === $monthAnchorDate) id="calendar-month-anchor" @endif
                        class="{{ $dayCellClasses }}"
                        :class="{ 'bg-indigo-50 dark:bg-indigo-950/30': isSelected('{{ $day['date'] }}') }"
                        x-on:mousedown.prevent="startSelect('{{ $day['date'] }}', $event)"
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
                                    $entryId = (int) str_replace($isMeeting ? 'meeting-' : 'event-', '', $entry->id);
                                    $clickAction = $isMeeting
                                        ? 'openShowMeeting('.$entryId.')'
                                        : 'openShowEvent('.$entryId.')';
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
                                $entryId = (int) str_replace($isMeeting ? 'meeting-' : 'event-', '', $entry->id);
                                $barClick = $isMeeting
                                    ? 'openShowMeeting('.$entryId.')'
                                    : 'openShowEvent('.$entryId.')';
                                if (! $bar['segmentEnd']) {
                                    $barClasses .= $isMeeting
                                        ? ' border-r border-blue-200 dark:border-blue-800/60'
                                        : ' border-r border-emerald-200 dark:border-emerald-800/60';
                                }
                            @endphp
                            <button
                                type="button"
                                wire:key="calendar-bar-{{ $weekKey }}-{{ $entry->id }}-{{ $bar['startCol'] }}"
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

    <div class="mt-8 rounded-lg border border-gray-200 bg-white p-4 shadow-sm sm:p-6 dark:border-gray-700 dark:bg-gray-800">
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

        <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
            In Google Calendar: Settings &rarr; Add calendar &rarr; From URL, then paste the copied feed URL.
        </p>
    </div>

    @if ($showEntryModal)
        <div class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0" wire:keydown.escape.window="closeEntryModal">
            <div class="fixed inset-0 bg-gray-500/75 dark:bg-gray-900/80" wire:click="closeEntryModal"></div>

            <div class="relative mx-auto max-w-lg rounded-lg bg-white p-4 shadow-xl sm:p-6 dark:bg-gray-800">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            {{ $viewTypeLabel }}
                            @if ($viewStatusLabel)
                                · {{ $viewStatusLabel }}
                            @endif
                        </p>
                        <h3 class="mt-1 text-lg font-semibold text-gray-900 dark:text-gray-100">
                            {{ $viewTitle }}
                        </h3>
                    </div>

                    @if ($viewingCanEdit)
                        @if ($viewingEntryKind === 'event')
                            <button
                                type="button"
                                wire:click="editEventFromView"
                                class="shrink-0 rounded p-1 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                                title="Edit event"
                            >
                                <span class="sr-only">Edit event</span>
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                </svg>
                            </button>
                        @else
                            <a
                                href="{{ $viewEditUrl }}"
                                wire:navigate
                                class="shrink-0 rounded p-1 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                                title="Edit meeting"
                            >
                                <span class="sr-only">Edit meeting</span>
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                </svg>
                            </a>
                        @endif
                    @endif
                </div>

                <dl class="mt-4 space-y-4 text-sm">
                    <div>
                        <dt class="font-medium text-gray-900 dark:text-gray-100">When</dt>
                        <dd class="mt-1 text-gray-600 dark:text-gray-400">{!! $viewScheduleLabel !!}</dd>
                    </div>

                    @if ($viewLocation)
                        <div>
                            <dt class="font-medium text-gray-900 dark:text-gray-100">Location</dt>
                            <dd class="mt-1 text-gray-600 dark:text-gray-400">{{ $viewLocation }}</dd>
                        </div>
                    @endif

                    @if ($viewVirtualLink)
                        <div>
                            <dt class="font-medium text-gray-900 dark:text-gray-100">Virtual meeting</dt>
                            <dd class="mt-1">
                                <a href="{{ $viewVirtualLink }}" target="_blank" rel="noopener noreferrer"
                                   class="text-indigo-600 hover:text-indigo-500 dark:text-indigo-400">
                                    Join virtual meeting
                                </a>
                            </dd>
                        </div>
                    @endif

                    @if ($viewDescription)
                        <div>
                            <dt class="font-medium text-gray-900 dark:text-gray-100">
                                {{ $viewingEntryKind === 'meeting' ? 'Agenda notes' : 'Notes' }}
                            </dt>
                            <dd class="mt-1 whitespace-pre-wrap text-gray-600 dark:text-gray-400">{{ $viewDescription }}</dd>
                        </div>
                    @endif
                </dl>

                <div class="mt-6 flex flex-wrap items-center justify-end gap-3">
                    @if ($viewEntryUrl)
                        <a href="{{ $viewEntryUrl }}" wire:navigate class="text-sm font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400">
                            Open meeting
                        </a>
                    @endif
                    <x-secondary-button type="button" wire:click="closeEntryModal" no-spinner>
                        Close
                    </x-secondary-button>
                </div>
            </div>
        </div>
    @endif

    @if ($showEventModal)
        <div class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0" wire:keydown.escape.window="closeEventModal">
            <div class="fixed inset-0 bg-gray-500/75 dark:bg-gray-900/80" wire:click="closeEventModal"></div>

            <div class="relative mx-auto max-w-lg rounded-lg bg-white p-4 shadow-xl sm:p-6 dark:bg-gray-800">
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
                                <x-input id="endDate" type="date" class="mt-1 block w-full" wire:model.live="endDate" min="{{ $startDate }}" required />
                                <x-input-error for="endDate" class="mt-2" />
                            </div>
                        </div>
                    @else
                        <div>
                            <x-label for="startsAtLocal" value="Starts" />
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">({{ $inputTimezone }})</p>
                            @if ($inputTeamTimezoneHint)
                                <p class="text-xs text-gray-500 dark:text-gray-400">Team time zone: {{ $inputTeamTimezoneHint }}</p>
                            @endif
                            <x-input id="startsAtLocal" type="datetime-local" class="mt-1 block w-full" wire:model.live="startsAtLocal" required />
                            <x-input-error for="startsAtLocal" class="mt-2" />
                        </div>

                        <div>
                            <x-label for="endsAtLocal" value="Ends" />
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">({{ $inputTimezone }})</p>
                            @if ($inputTeamTimezoneHint)
                                <p class="text-xs text-gray-500 dark:text-gray-400">Team time zone: {{ $inputTeamTimezoneHint }}</p>
                            @endif
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
