<div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Action items</h4>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                @if ($canManageActionItems)
                    Follow-up tasks from this meeting. Assign council members and track completion.
                @else
                    Action items assigned to you from this meeting.
                @endif
            </p>
        </div>
        @if ($canManageActionItems)
            <x-secondary-button type="button" wire:click="openCreateModal" no-spinner>
                Add action item
            </x-secondary-button>
        @endif
    </div>

    <div class="mt-4 space-y-3">
        @forelse ($actionItems as $actionItem)
            <div class="rounded-md border border-gray-200 px-4 py-3 dark:border-gray-600 {{ $actionItem->isOverdue() ? 'border-red-300 bg-red-50/50 dark:border-red-800 dark:bg-red-950/20' : '' }}">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $actionItem->title }}</p>
                            <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700 dark:bg-gray-700 dark:text-gray-300">
                                {{ $actionItem->status->label() }}
                            </span>
                            @if ($actionItem->isOverdue())
                                <span class="rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-800 dark:bg-red-900/40 dark:text-red-300">
                                    Overdue
                                </span>
                            @endif
                        </div>

                        @if ($actionItem->description)
                            <p class="mt-2 whitespace-pre-wrap text-sm text-gray-600 dark:text-gray-400">{{ $actionItem->description }}</p>
                        @endif

                        <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-gray-500 dark:text-gray-400">
                            @if ($actionItem->assignee)
                                <span>Assigned to {{ $actionItem->assignee->name }}</span>
                            @endif
                            @if ($actionItem->due_at)
                                <span>Due {{ \Afterburner\Meetings\Support\TeamDateTime::format($team, $actionItem->due_at, 'M j, Y') }}</span>
                            @endif
                            @if ($actionItem->completed_at)
                                <span>Completed {{ \Afterburner\Meetings\Support\TeamDateTime::format($team, $actionItem->completed_at) }}</span>
                            @endif
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-2 shrink-0">
                        @if ($canManageActionItems)
                            <x-secondary-button type="button" wire:click="openEditModal({{ $actionItem->id }})" no-spinner>
                                Edit
                            </x-secondary-button>
                            @if ($actionItem->status->isOpen())
                                <x-button type="button" wire:click="completeActionItem({{ $actionItem->id }})" no-spinner>
                                    Complete
                                </x-button>
                            @endif
                            <button type="button"
                                    wire:click="deleteActionItem({{ $actionItem->id }})"
                                    wire:confirm="Delete this action item?"
                                    class="p-1 text-gray-400 hover:text-red-600 dark:hover:text-red-400 rounded"
                                    title="Delete action item">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                </svg>
                            </button>
                        @elseif ($actionItem->status->isOpen())
                            <select wire:change="updateStatus({{ $actionItem->id }}, $event.target.value)"
                                    class="rounded-md border-gray-300 text-xs shadow-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100">
                                @foreach ($statusOptions as $option)
                                    <option value="{{ $option->value }}" @selected($actionItem->status === $option)>{{ $option->label() }}</option>
                                @endforeach
                            </select>
                            @if ($actionItem->status !== \Afterburner\Meetings\Enums\ActionItemStatus::Completed)
                                <x-button type="button" wire:click="completeActionItem({{ $actionItem->id }})" no-spinner>
                                    Mark complete
                                </x-button>
                            @endif
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <p class="text-sm text-gray-500 dark:text-gray-400">
                @if ($canManageActionItems)
                    No action items yet.
                @else
                    No action items assigned to you.
                @endif
            </p>
        @endforelse
    </div>

    <x-dialog-modal wire:model.live="showFormModal">
        <x-slot name="title">{{ $editingActionItemId ? 'Edit action item' : 'Add action item' }}</x-slot>
        <x-slot name="content">
            <div class="space-y-4">
                <div>
                    <x-label for="action-item-title" value="Title" />
                    <x-input id="action-item-title" type="text" class="mt-1 block w-full" wire:model="title" />
                    <x-input-error for="title" class="mt-2" />
                </div>

                <div>
                    <x-label for="action-item-description" value="Description" />
                    <textarea id="action-item-description" rows="3" wire:model="description"
                              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100"></textarea>
                </div>

                <div>
                    <x-label for="action-item-assignee" value="Assign to" />
                    <select id="action-item-assignee" wire:model="assignedToUserId"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100">
                        <option value="">Unassigned</option>
                        @foreach ($teamMembers as $member)
                            <option value="{{ $member->id }}">{{ $member->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <x-label for="action-item-due-at" value="Due date" />
                    <x-input id="action-item-due-at" type="date" class="mt-1 block w-full" wire:model="dueAt" />
                </div>

                @if ($editingActionItemId)
                    <div>
                        <x-label for="action-item-status" value="Status" />
                        <select id="action-item-status" wire:model="status"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100">
                            @foreach ($statusOptions as $option)
                                <option value="{{ $option->value }}">{{ $option->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
            </div>
        </x-slot>
        <x-slot name="footer">
            <x-secondary-button wire:click="closeFormModal">Cancel</x-secondary-button>
            <x-button class="ms-3" wire:click="saveActionItem" no-spinner>
                {{ $editingActionItemId ? 'Save changes' : 'Add action item' }}
            </x-button>
        </x-slot>
    </x-dialog-modal>
</div>
