<div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
    @if (! $embedded || $canManageAgenda)
    <div class="flex flex-wrap items-center justify-between gap-3 {{ $embedded ? 'justify-end' : '' }}">
        @unless ($embedded)
            <div>
                <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Agenda</h4>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    @if ($canManageAgenda)
                        Build the meeting agenda manually or link existing records so you do not have to retype topics.
                    @else
                        Topics scheduled for discussion at this meeting.
                    @endif
                </p>
            </div>
        @endunless
        @if ($canManageAgenda)
            <div class="flex flex-wrap gap-2">
                @if ($hasSuggestions)
                    <x-secondary-button type="button" wire:click="addSuggestedItems" no-spinner>
                        Add suggested items
                    </x-secondary-button>
                @endif
                @if ($availableProviders !== [])
                    <x-secondary-button type="button" wire:click="openLinkModal" no-spinner>
                        Link existing record
                    </x-secondary-button>
                @endif
                <x-secondary-button type="button" wire:click="openCreateModal" no-spinner>
                    Add agenda item
                </x-secondary-button>
            </div>
        @endif
    </div>
    @endif

    <div class="{{ (! $embedded || $canManageAgenda) ? 'mt-4' : '' }} space-y-3"
         @if ($canManageAgenda && $agendaItems->isNotEmpty())
             x-data="{ draggingId: null }"
         @endif>
        @forelse ($agendaItems as $index => $item)
            <div wire:key="agenda-item-{{ $item->id }}"
                 class="rounded-md border border-gray-200 px-4 py-3 dark:border-gray-600"
                 @if ($canManageAgenda)
                     @dragover.prevent
                     @drop.prevent="if (draggingId !== null) { $wire.sortAgendaItem(draggingId, {{ $index }}); draggingId = null; }"
                 @endif>
                <div class="flex items-start gap-3">
                    @if ($canManageAgenda)
                        <div draggable="true"
                             @dragstart="draggingId = {{ $item->id }}; $event.dataTransfer.effectAllowed = 'move'"
                             @dragend="draggingId = null"
                             class="mt-0.5 cursor-grab p-1 text-gray-400 hover:text-gray-600 active:cursor-grabbing dark:hover:text-gray-300"
                             title="Drag to reorder">
                            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <circle cx="9" cy="6" r="1.5"></circle>
                                <circle cx="15" cy="6" r="1.5"></circle>
                                <circle cx="9" cy="12" r="1.5"></circle>
                                <circle cx="15" cy="12" r="1.5"></circle>
                                <circle cx="9" cy="18" r="1.5"></circle>
                                <circle cx="15" cy="18" r="1.5"></circle>
                            </svg>
                        </div>
                    @endif

                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-xs font-medium text-gray-400 dark:text-gray-500">{{ $index + 1 }}.</span>
                            <p class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $item->title }}</p>
                        </div>

                        @if ($item->displaySummary())
                            <p class="mt-2 whitespace-pre-wrap text-sm text-gray-600 dark:text-gray-400">{{ $item->displaySummary() }}</p>
                        @endif
                    </div>

                    @if ($canManageAgenda || $item->referenceViewUrl())
                        <div class="flex shrink-0 items-center gap-2">
                            @if ($canManageAgenda)
                                <button type="button"
                                        wire:click="openEditModal({{ $item->id }})"
                                        class="rounded p-1 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                                        title="Edit agenda item">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                    </svg>
                                </button>
                                <button type="button"
                                        wire:click="deleteAgendaItem({{ $item->id }})"
                                        wire:confirm="Remove this agenda item?"
                                        class="rounded p-1 text-gray-400 hover:text-red-600 dark:hover:text-red-400"
                                        title="Remove agenda item">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                    </svg>
                                </button>
                            @endif
                            @if ($item->referenceViewUrl())
                                <a href="{{ $item->referenceViewUrl() }}"
                                   class="rounded p-1 text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400"
                                   title="View linked record">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                    </svg>
                                </a>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        @empty
            <p class="text-sm text-gray-500 dark:text-gray-400">
                @if ($canManageAgenda)
                    No agenda items yet. Link existing records or add items manually.
                @else
                    No agenda items have been added yet.
                @endif
            </p>
        @endforelse
    </div>

    <x-dialog-modal wire:model.live="showFormModal">
        <x-slot name="title">{{ $editingAgendaItemId ? 'Edit agenda item' : 'Add agenda item' }}</x-slot>
        <x-slot name="content">
            <div class="space-y-4">
                <div>
                    <x-label for="agenda-item-title" value="Title" />
                    <x-input id="agenda-item-title" type="text" class="mt-1 block w-full" wire:model.live="title" />
                    <x-input-error for="title" class="mt-2" />
                </div>

                <div>
                    <x-label for="agenda-item-notes" value="Notes" />
                    <textarea id="agenda-item-notes" rows="3" wire:model.live="notes"
                              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100"></textarea>
                </div>

                <div>
                    <x-label for="agenda-item-section" value="Section" />
                    <select id="agenda-item-section" wire:model.live="section"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100">
                        <option value="">None</option>
                        @foreach ($sectionOptions as $option)
                            <option value="{{ $option->value }}">{{ $option->label() }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </x-slot>
        <x-slot name="footer">
            <x-secondary-button wire:click="closeFormModal">Cancel</x-secondary-button>
            <x-button class="ms-3" wire:click="saveAgendaItem" no-spinner>
                {{ $editingAgendaItemId ? 'Save changes' : 'Add agenda item' }}
            </x-button>
        </x-slot>
    </x-dialog-modal>

    <x-dialog-modal wire:model.live="showLinkModal">
        <x-slot name="title">Link existing record</x-slot>
        <x-slot name="content">
            <div class="space-y-4">
                <div>
                    <x-label for="link-provider-key" value="Record type" />
                    <select id="link-provider-key" wire:model.live="linkProviderKey"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100">
                        <option value="">Select a type</option>
                        @foreach ($availableProviders as $provider)
                            <option value="{{ $provider->key() }}">{{ $provider->label() }}</option>
                        @endforeach
                    </select>
                    <x-input-error for="linkProviderKey" class="mt-2" />
                </div>

                @if ($linkProviderKey)
                    <div>
                        <x-label for="reference-search" value="Search" />
                        <x-input id="reference-search" type="text" class="mt-1 block w-full" wire:model.live.debounce.300ms="referenceSearch" placeholder="Search by title..." />
                    </div>

                    <div>
                        <x-label for="selected-reference-id" value="Record" />
                        <select id="selected-reference-id" wire:model.live="selectedReferenceId"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100">
                            <option value="">Select a record</option>
                            @foreach ($searchResults as $result)
                                <option value="{{ $result->getKey() }}">{{ $result->getAttribute('title') ?? $result->getKey() }}</option>
                            @endforeach
                        </select>
                        <x-input-error for="selectedReferenceId" class="mt-2" />
                    </div>
                @endif

                <div>
                    <x-label for="link-section" value="Section" />
                    <select id="link-section" wire:model="section"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100">
                        <option value="">None</option>
                        @foreach ($sectionOptions as $option)
                            <option value="{{ $option->value }}">{{ $option->label() }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </x-slot>
        <x-slot name="footer">
            <x-secondary-button wire:click="closeLinkModal">Cancel</x-secondary-button>
            <x-button class="ms-3" wire:click="linkReference" no-spinner>
                Add to agenda
            </x-button>
        </x-slot>
    </x-dialog-modal>
</div>
