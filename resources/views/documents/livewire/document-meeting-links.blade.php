<div>
    <x-dialog-modal wire:model.live="showModal">
        <x-slot name="title">
            @if ($document)
                Link “{{ $document->name }}” to meetings
            @else
                Link to meetings
            @endif
        </x-slot>
        <x-slot name="content">
            @if ($document)
                @include('afterburner-meetings::components.meeting-attach-search-modal', [
                    'searchWireModel' => 'meetingSearch',
                    'searchPlaceholder' => 'Search meetings to link...',
                    'searchResults' => $searchMeetings,
                    'attachedItems' => $linkedMeetings,
                    'attachMethod' => 'attachToMeeting',
                    'detachMethod' => 'detachFromMeeting',
                    'searchIsActive' => $searchIsActive,
                    'attachIdParameter' => 'id',
                    'resultLabel' => 'title',
                    'attachedHeading' => 'Linked meetings',
                    'attachedEmptyMessage' => 'Not linked to any meetings yet.',
                    'noResultsMessage' => 'No matching meetings found.',
                ])
            @endif
        </x-slot>
        <x-slot name="footer">
            <x-secondary-button wire:click="closeModal">Done</x-secondary-button>
        </x-slot>
    </x-dialog-modal>
</div>
