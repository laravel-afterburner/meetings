<div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
    <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Linked ballots</h4>
    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Ballots linked to this meeting.</p>

    <div class="mt-4 space-y-3">
        @foreach ($linkedBallots as $ballot)
            @php
                $event = $ballotEvents[(string) $ballot->id] ?? null;
            @endphp
            <div class="rounded-md border border-gray-200 px-4 py-3 dark:border-gray-600">
                <a href="{{ route('teams.ballots.show', ['team' => $team, 'ballot' => $ballot]) }}"
                   wire:navigate
                   class="text-sm font-medium text-gray-900 hover:text-indigo-600 dark:text-gray-100 dark:hover:text-indigo-400">
                    {{ $ballot->title }}
                </a>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    {{ $ballot->status->label() }}
                    @if ($event)
                        · Last event: {{ $event['event'] }}
                    @endif
                </p>
            </div>
        @endforeach
    </div>
</div>
