<div>
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div class="w-full sm:w-auto sm:min-w-[12rem]">
            <x-label for="meetingTab" value="Show" />
            <x-select-input id="meetingTab" wire:model.live="tab" class="mt-1 block w-full">
                <option value="upcoming">Upcoming</option>
                <option value="past">Past</option>
                <option value="draft">Drafts</option>
            </x-select-input>
        </div>

        @if ($canCreate)
            <x-button wire:click="createMeeting" no-spinner>
                Create meeting
            </x-button>
        @endif
    </div>

    <div class="overflow-hidden bg-white shadow sm:rounded-lg dark:bg-gray-800">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-900">
                <tr>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                        Meeting
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                        Type
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                        Status
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                        Scheduled
                    </th>
                    <th scope="col" class="relative px-6 py-3">
                        <span class="sr-only">Actions</span>
                    </th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-800">
                @forelse ($meetings as $meeting)
                    <tr class="group hover:bg-gray-50 dark:hover:bg-gray-700/50" wire:key="meeting-row-{{ $meeting->id }}">
                        <td class="px-6 py-4">
                            <button
                                type="button"
                                wire:click="viewMeeting({{ $meeting->id }})"
                                class="text-left text-sm font-medium text-gray-900 hover:text-indigo-600 dark:text-gray-100 dark:hover:text-indigo-400"
                            >
                                {{ $meeting->title }}
                            </button>
                            @if (($meeting->overdue_action_items_count ?? 0) > 0)
                                <span class="mt-1 inline-flex rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-800 dark:bg-red-900/40 dark:text-red-300">
                                    {{ $meeting->overdue_action_items_count }} overdue
                                </span>
                            @endif
                        </td>
                        <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-600 dark:text-gray-400">
                            {{ $meeting->type->label() }}
                        </td>
                        <td class="whitespace-nowrap px-6 py-4 text-sm">
                            <span @class([
                                'inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium',
                                $meeting->status->badgeClasses(),
                            ])>
                                {{ $meeting->status->label() }}
                            </span>
                        </td>
                        <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-600 dark:text-gray-400">
                            @if ($meeting->scheduled_at)
                                {!! \Afterburner\Meetings\Support\TeamDateTime::formatDisplay($team, $meeting->scheduled_at) !!}
                            @else
                                —
                            @endif
                        </td>
                        <td class="whitespace-nowrap px-6 py-4 text-right text-sm font-medium">
                            <div class="flex items-center justify-end space-x-2">
                                <x-action-icon type="view" wire:click="viewMeeting({{ $meeting->id }})" title="View meeting" />
                                @can('update', $meeting)
                                    <x-action-icon type="edit" wire:click="editMeeting({{ $meeting->id }})" title="Edit meeting" />
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-6 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                            No meetings in this list yet.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        {{ $meetings->links() }}
    </div>
</div>
