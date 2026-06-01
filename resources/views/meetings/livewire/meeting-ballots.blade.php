<div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
    @if (! $embedded || $canLinkBallots)
    <div class="flex flex-wrap items-center justify-between gap-3 {{ $embedded ? 'justify-end' : '' }}">
        @unless ($embedded)
            <div>
                <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Linked ballots</h4>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    @if ($canLinkBallots)
                        Reference ballots for this meeting. Voting mechanics remain in the voting package.
                    @else
                        Ballots linked to this meeting for reference during the session.
                    @endif
                </p>
            </div>
        @endunless
        @if ($canLinkBallots)
            <x-secondary-button type="button" wire:click="openLinkModal" no-spinner>
                Link ballot
            </x-secondary-button>
        @endif
    </div>
    @endif

    <div class="{{ (! $embedded || $canLinkBallots) ? 'mt-4' : '' }} space-y-3">
        @forelse ($linkedBallots as $ballot)
            @php
                $event = $ballotEvents[(string) $ballot->id] ?? null;
            @endphp
            <div class="flex items-center justify-between gap-4 rounded-md border border-gray-200 px-4 py-3 dark:border-gray-600">
                <div class="min-w-0">
                    <a href="{{ route('teams.ballots.show', ['team' => $team, 'ballot' => $ballot]) }}"
                       wire:navigate
                       class="truncate text-sm font-medium text-gray-900 hover:text-indigo-600 dark:text-gray-100 dark:hover:text-indigo-400">
                        {{ $ballot->title }}
                    </a>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        {{ $ballot->status->label() }}
                        @if ($event)
                            · Last event: {{ $event['event'] }}
                        @endif
                    </p>
                </div>
                @if ($canLinkBallots)
                    <button type="button"
                            wire:click="unlinkBallot({{ $ballot->id }})"
                            wire:loading.attr="disabled"
                            class="p-1 text-gray-400 hover:text-red-600 dark:hover:text-red-400 rounded shrink-0"
                            title="Unlink ballot">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                        </svg>
                    </button>
                @endif
            </div>
        @empty
            <p class="text-sm text-gray-500 dark:text-gray-400">No ballots linked yet.</p>
        @endforelse
    </div>

    <x-dialog-modal wire:model.live="showLinkModal">
        <x-slot name="title">Link ballot</x-slot>
        <x-slot name="content">
            <x-input type="search" class="w-full" wire:model.live.debounce.300ms="ballotSearch" placeholder="Search ballots..." />
            <div class="mt-4 max-h-64 space-y-2 overflow-y-auto">
                @forelse ($availableBallots as $ballot)
                    <button type="button"
                            wire:click="linkBallot({{ $ballot->id }})"
                            class="flex w-full items-center justify-between rounded-md border border-gray-200 px-3 py-2 text-left text-sm hover:bg-gray-50 dark:border-gray-600 dark:hover:bg-gray-700">
                        <span>{{ $ballot->title }}</span>
                        <span class="text-xs text-gray-500">{{ $ballot->status->label() }}</span>
                    </button>
                @empty
                    <p class="text-sm text-gray-500 dark:text-gray-400">No ballots available to link.</p>
                @endforelse
            </div>
        </x-slot>
        <x-slot name="footer">
            <x-secondary-button wire:click="closeLinkModal">Cancel</x-secondary-button>
        </x-slot>
    </x-dialog-modal>
</div>
