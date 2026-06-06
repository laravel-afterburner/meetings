<div>
    @if ($canCreate)
        <x-page-actions>
            <x-button href="{{ route('teams.meetings.create', ['team' => $team]) }}" wire:navigate>
                Create meeting
            </x-button>
        </x-page-actions>
    @endif

    <div class="overflow-hidden rounded-lg bg-white shadow dark:bg-gray-800">
        <x-responsive-table :bleed="false">
            <thead class="bg-gray-50 dark:bg-gray-900">
                <tr>
                    <th scope="col" class="text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                        Meeting
                    </th>
                    <th scope="col" class="table-cell-md text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                        Type
                    </th>
                    <th scope="col" class="text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                        Status
                    </th>
                    <th scope="col" class="table-cell-md text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                        Scheduled
                    </th>
                    <th scope="col" class="relative">
                        <span class="sr-only">Actions</span>
                    </th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-800">
                @forelse ($meetings as $meeting)
                    @php
                        $canEdit = auth()->user()->can('update', $meeting)
                            && in_array($meeting->status, [\Afterburner\Meetings\Enums\MeetingStatus::Draft, \Afterburner\Meetings\Enums\MeetingStatus::Scheduled], true);
                        $canStart = auth()->user()->can('start', $meeting);
                        $canContinue = $meeting->status === \Afterburner\Meetings\Enums\MeetingStatus::InProgress
                            && (auth()->user()->can('conductSession', $meeting) || auth()->user()->can('complete', $meeting));
                        $canWrapUp = auth()->user()->can('reviseAfterCompletion', $meeting);
                    @endphp
                    <tr class="group hover:bg-gray-50 dark:hover:bg-gray-700/50" wire:key="meeting-row-{{ $meeting->id }}">
                        <td>
                            <a
                                href="{{ route('teams.meetings.show', ['team' => $team, 'meeting' => $meeting]) }}"
                                wire:navigate
                                class="text-left text-sm font-medium text-gray-900 hover:text-indigo-600 dark:text-gray-100 dark:hover:text-indigo-400"
                            >
                                {{ $meeting->title }}
                            </a>
                            @if (($meeting->overdue_action_items_count ?? 0) > 0)
                                <span class="mt-1 inline-flex rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-800 dark:bg-red-900/40 dark:text-red-300">
                                    {{ $meeting->overdue_action_items_count }} overdue
                                </span>
                            @endif
                        </td>
                        <td class="table-cell-md whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">
                            {{ $meeting->type->label() }}
                        </td>
                        <td class="whitespace-nowrap text-sm">
                            <span @class([
                                'inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium',
                                $meeting->status->badgeClasses(),
                            ])>
                                {{ $meeting->status->label() }}
                            </span>
                        </td>
                        <td class="table-cell-md whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">
                            @if ($meeting->scheduled_at)
                                {!! \App\Support\TeamDateTime::formatDisplay($team, $meeting->scheduled_at) !!}
                            @else
                                Pending
                            @endif
                        </td>
                        <td class="whitespace-nowrap text-right text-sm font-medium">
                            <div class="flex items-center justify-end gap-1">
                                <x-afterburner-meetings::meeting-nav-icon
                                    action="view"
                                    :href="route('teams.meetings.show', ['team' => $team, 'meeting' => $meeting])"
                                    title="View meeting"
                                    wire:navigate
                                />
                                @if ($canEdit)
                                    <x-afterburner-meetings::meeting-nav-icon
                                        action="edit"
                                        :href="route('teams.meetings.edit', ['team' => $team, 'meeting' => $meeting])"
                                        title="Edit meeting"
                                        wire:navigate
                                    />
                                @endif
                                @if ($canStart)
                                    <x-afterburner-meetings::meeting-nav-icon
                                        action="start"
                                        :href="route('teams.meetings.show', ['team' => $team, 'meeting' => $meeting])"
                                        title="Start meeting"
                                        wire:navigate
                                    />
                                @endif
                                @if ($canContinue)
                                    <x-afterburner-meetings::meeting-nav-icon
                                        action="continue"
                                        :href="route('teams.meetings.in-progress', ['team' => $team, 'meeting' => $meeting])"
                                        title="Continue meeting"
                                        wire:navigate
                                    />
                                @endif
                                @if ($canWrapUp)
                                    <x-afterburner-meetings::meeting-nav-icon
                                        action="wrap-up"
                                        :href="route('teams.meetings.completed', ['team' => $team, 'meeting' => $meeting])"
                                        title="Wrap up meeting"
                                        wire:navigate
                                    />
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                            No meetings yet.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </x-responsive-table>
    </div>

    <div class="mt-6">
        {{ $meetings->links() }}
    </div>
</div>
