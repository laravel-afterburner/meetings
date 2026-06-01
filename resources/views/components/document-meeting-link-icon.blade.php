@props(['document', 'team'])

@php
    use Afterburner\Meetings\Support\MeetingsDocumentLinkUi;

    $teamModel = $team instanceof \App\Models\Team ? $team : \App\Models\Team::query()->find($team);
    $showLink = $teamModel
        && auth()->user()
        && MeetingsDocumentLinkUi::canShowLinkAction(auth()->user(), $teamModel, $document);
@endphp

@if ($showLink)
    <button
        type="button"
        wire:click="$dispatch('open-document-meeting-link-modal', { documentId: {{ $document->id }}, teamId: {{ $teamModel->id }} })"
        class="rounded p-1 text-gray-400 transition hover:text-indigo-600 dark:hover:text-indigo-400"
        title="Link to meeting"
    >
        <span class="sr-only">Link to meeting</span>
        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path>
        </svg>
    </button>
@endif
