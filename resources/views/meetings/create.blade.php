<x-app-layout :title="\Afterburner\Meetings\Support\PageHeader::make('Events', isset($meeting) ? 'Edit' : 'Create meeting', isset($meeting) ? $meeting->title : null)">
    <x-slot name="header">
        @if (isset($meeting))
            <x-afterburner-meetings::page-header section="Events" action="Edit" :detail="$meeting->title" />
        @else
            <x-afterburner-meetings::page-header section="Events" action="Create meeting" />
        @endif
    </x-slot>

    <div class="max-w-4xl mx-auto py-10 sm:px-6 lg:px-8">
        @livewire('meetings.create', array_filter([
            'team' => $team,
            'meetingId' => isset($meeting) ? $meeting->id : null,
        ], fn ($value) => $value !== null))
    </div>
</x-app-layout>
