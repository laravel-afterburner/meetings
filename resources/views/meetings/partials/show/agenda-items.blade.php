<div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
    <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Agenda</h4>
    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Topics scheduled for discussion at this meeting.</p>

    <div class="mt-4 space-y-3">
        @foreach ($agendaItems as $index => $item)
            <div class="rounded-md border border-gray-200 px-4 py-3 dark:border-gray-600">
                <div class="flex items-start gap-3">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-xs font-medium text-gray-400 dark:text-gray-500">{{ $index + 1 }}.</span>
                            <p class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $item->title }}</p>
                        </div>
                        @if ($item->displaySummary())
                            <p class="mt-2 whitespace-pre-wrap text-sm text-gray-600 dark:text-gray-400">{{ $item->displaySummary() }}</p>
                        @endif
                    </div>
                    @if ($item->referenceViewUrl())
                        <a href="{{ $item->referenceViewUrl() }}"
                           class="shrink-0 rounded p-1 text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400"
                           title="View linked record">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                            </svg>
                        </a>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</div>
