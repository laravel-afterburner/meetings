@props(['meeting'])

@if ($meeting->minutes_finalized_at)
    <span class="inline-flex rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-800 dark:bg-green-900/40 dark:text-green-300">
        Finalized
    </span>
@elseif (filled($meeting->minutes))
    <span class="inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800 dark:bg-amber-900/40 dark:text-amber-300">
        Draft
    </span>
@endif
