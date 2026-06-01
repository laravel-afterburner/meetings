<div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
    <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Action items</h4>
    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Follow-up tasks recorded for this meeting.</p>

    <div class="mt-4 space-y-3">
        @foreach ($actionItems as $actionItem)
            <div class="rounded-md border border-gray-200 px-4 py-3 dark:border-gray-600 {{ $actionItem->isOverdue() ? 'border-red-300 bg-red-50/50 dark:border-red-800 dark:bg-red-950/20' : '' }}">
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
                        <span>Due {!! \Afterburner\Meetings\Support\TeamDateTime::formatDisplay($team, $actionItem->due_at, false) !!}</span>
                    @endif
                    @if ($actionItem->completed_at)
                        <span>Completed {!! \Afterburner\Meetings\Support\TeamDateTime::formatDisplay($team, $actionItem->completed_at) !!}</span>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</div>
