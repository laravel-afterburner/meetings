<div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
    <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Meeting documents</h4>
    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Documents attached to this meeting.</p>

    <div class="mt-4 space-y-3">
        @foreach ($linkedDocuments as $document)
            <div class="flex min-w-0 items-start gap-3 rounded-md border border-gray-200 px-4 py-3 dark:border-gray-600">
                {!! $document->getIconSvg() !!}
                <p class="truncate text-sm font-medium text-gray-900 dark:text-gray-100">{{ $document->name }}</p>
            </div>
        @endforeach
    </div>
</div>
