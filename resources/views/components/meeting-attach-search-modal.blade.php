@php
    $emptySearchMessage ??= __('Type at least 2 characters to search.');
    $noResultsMessage ??= __('No results found.');
    $attachedHeading ??= __('Attached');
    $attachedEmptyMessage ??= __('Nothing attached yet.');
    $attachIdParameter ??= 'id';
    $resultLabel ??= 'name';
@endphp

<div class="space-y-4">
    <x-input
        type="search"
        class="w-full"
        wire:model.live.debounce.300ms="{{ $searchWireModel }}"
        placeholder="{{ $searchPlaceholder }}"
    />

    @if (! $searchIsActive)
        <p class="text-sm text-gray-500 dark:text-gray-400">{{ $emptySearchMessage }}</p>
    @else
        <div class="max-h-40 space-y-2 overflow-y-auto">
            @forelse ($searchResults as $result)
                <button
                    type="button"
                    wire:click="{{ $attachMethod }}({{ $result->{$attachIdParameter} }})"
                    class="flex w-full rounded-md border border-gray-200 px-3 py-2 text-left text-sm hover:bg-gray-50 dark:border-gray-600 dark:hover:bg-gray-700"
                >
                    {{ $result->{$resultLabel} }}
                </button>
            @empty
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ $noResultsMessage }}</p>
            @endforelse
        </div>
    @endif

    <div class="border-t border-gray-200 pt-4 dark:border-gray-700">
        <h5 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $attachedHeading }}</h5>
        <div class="mt-3 max-h-48 space-y-2 overflow-y-auto">
            @forelse ($attachedItems as $item)
                <div class="flex items-center justify-between gap-3 rounded-md border border-gray-200 px-3 py-2 dark:border-gray-600">
                    <span class="min-w-0 truncate text-sm text-gray-900 dark:text-gray-100">{{ $item->{$resultLabel} }}</span>
                    <button
                        type="button"
                        wire:click="{{ $detachMethod }}({{ $item->{$attachIdParameter} }})"
                        wire:loading.attr="disabled"
                        class="shrink-0 rounded p-1 text-gray-400 hover:text-red-600 dark:hover:text-red-400"
                        title="Remove"
                    >
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                        </svg>
                    </button>
                </div>
            @empty
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ $attachedEmptyMessage }}</p>
            @endforelse
        </div>
    </div>
</div>
