<div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
    @if (! $embedded || $canManageDocuments)
    <div class="flex flex-wrap items-center justify-between gap-3 {{ $embedded ? 'justify-end' : '' }}">
        @unless ($embedded)
            <div>
                <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Meeting documents</h4>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    @if ($canManageDocuments)
                        Agendas, notices, and supporting materials for this meeting.
                    @else
                        Documents attached to this meeting.
                    @endif
                </p>
            </div>
        @endunless
        @if ($canManageDocuments)
            <x-secondary-button type="button" wire:click="openAttachModal" no-spinner>
                Attach document
            </x-secondary-button>
        @endif
    </div>
    @endif

    <div class="{{ (! $embedded || $canManageDocuments) ? 'mt-4' : '' }} space-y-3">
        @forelse ($linkedDocuments as $document)
            <div class="flex items-center justify-between gap-4 rounded-md border border-gray-200 px-4 py-3 dark:border-gray-600">
                <div class="flex min-w-0 flex-1 items-start gap-3">
                    {!! $document->getIconSvg() !!}
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium text-gray-900 dark:text-gray-100">{{ $document->name }}</p>
                    </div>
                </div>
                <div class="flex shrink-0 items-center gap-2">
                    @can('download', $document)
                        <a href="{{ route('teams.documents.download', ['team' => $team, 'document' => $document]) }}"
                           class="rounded p-1 text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400"
                           title="Download document">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5m0 0l5-5m-5 5V4"></path>
                            </svg>
                        </a>
                    @endcan
                    @if ($canManageDocuments)
                        <button type="button"
                                wire:click="detachDocument({{ $document->id }})"
                                wire:loading.attr="disabled"
                                class="shrink-0 rounded p-1 text-gray-400 hover:text-red-600 dark:hover:text-red-400"
                                title="Remove document">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                            </svg>
                        </button>
                    @endif
                </div>
            </div>
        @empty
            <p class="text-sm text-gray-500 dark:text-gray-400">No documents attached yet.</p>
        @endforelse
    </div>

    <x-dialog-modal wire:model.live="showAttachModal">
        <x-slot name="title">Attach documents</x-slot>
        <x-slot name="content">
            @include('afterburner-meetings::components.meeting-attach-search-modal', [
                'searchWireModel' => 'documentSearch',
                'searchPlaceholder' => 'Search documents to attach...',
                'searchResults' => $searchDocuments,
                'attachedItems' => $linkedDocuments,
                'attachMethod' => 'attachDocument',
                'detachMethod' => 'detachDocument',
                'searchIsActive' => $searchIsActive,
                'attachIdParameter' => 'id',
                'resultLabel' => 'name',
                'attachedHeading' => 'Attached to this meeting',
                'attachedEmptyMessage' => 'No documents attached to this meeting yet.',
                'noResultsMessage' => 'No matching documents found.',
            ])
        </x-slot>
        <x-slot name="footer">
            <x-secondary-button wire:click="closeAttachModal">Done</x-secondary-button>
        </x-slot>
    </x-dialog-modal>
</div>
