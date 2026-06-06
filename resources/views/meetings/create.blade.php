<x-app-layout :title="\Afterburner\Meetings\Support\PageHeader::make('Meeting', isset($meeting) ? 'Edit' : 'Create meeting', isset($meeting) ? $meeting->title : null)">
    <x-slot name="header">
        @if (isset($meeting))
            <x-afterburner-meetings::page-header section="Meeting" action="Edit" :detail="$meeting->title" />
        @else
            <x-afterburner-meetings::page-header section="Meeting" action="Create meeting" />
        @endif
    </x-slot>

    <div class="max-w-7xl mx-auto px-4 py-6 sm:py-10 sm:px-6 lg:px-8">
        @livewire('meetings.create', array_filter([
            'team' => $team,
            'meetingId' => isset($meeting) ? $meeting->id : null,
        ], fn ($value) => $value !== null))
    </div>
</x-app-layout>
