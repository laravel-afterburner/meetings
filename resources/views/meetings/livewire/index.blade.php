<div>
    @if ($canCreate)
        <div class="mb-6 flex items-center justify-end">
            <x-button wire:click="createMeeting" no-spinner>
                Create Meeting
            </x-button>
        </div>
    @endif

    <div class="mb-6 border-b border-gray-200 dark:border-gray-700">
        <nav class="-mb-px flex space-x-8">
            @foreach (['upcoming' => 'Upcoming', 'past' => 'Past', 'draft' => 'Drafts'] as $key => $label)
                <button wire:click="setTab('{{ $key }}')"
                        type="button"
                        class="whitespace-nowrap border-b-2 px-1 py-4 text-sm font-medium {{ $tab === $key ? 'border-gray-800 text-gray-900 dark:border-gray-200 dark:text-gray-100' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400 dark:hover:border-gray-600 dark:hover:text-gray-300' }}">
                    {{ $label }}
                </button>
            @endforeach
        </nav>
    </div>

    <div class="space-y-4">
        @forelse ($meetings as $meeting)
            <a href="{{ route('teams.meetings.show', ['team' => $team, 'meeting' => $meeting]) }}"
               wire:navigate
               class="block rounded-lg border border-gray-200 bg-white p-5 shadow-sm transition hover:border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:hover:border-gray-500">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $meeting->title }}</h3>
                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                            {{ $meeting->type->label() }} · {{ $meeting->status->label() }}
                            @if ($meeting->scheduled_at)
                                · {{ \Afterburner\Meetings\Support\TeamDateTime::format($team, $meeting->scheduled_at) }}
                            @endif
                        </p>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        @if (($meeting->overdue_action_items_count ?? 0) > 0)
                            <span class="rounded-full bg-red-100 px-3 py-1 text-xs font-medium text-red-800 dark:bg-red-900/40 dark:text-red-300">
                                {{ $meeting->overdue_action_items_count }} overdue
                            </span>
                        @endif
                        <span class="rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-700 dark:bg-gray-700 dark:text-gray-300">
                            {{ $meeting->status->label() }}
                        </span>
                    </div>
                </div>
            </a>
        @empty
            <div class="rounded-lg border border-dashed border-gray-300 p-8 text-center text-sm text-gray-500 dark:border-gray-600 dark:text-gray-400">
                No meetings in this tab yet.
            </div>
        @endforelse
    </div>

    <div class="mt-6">
        {{ $meetings->links() }}
    </div>
</div>
