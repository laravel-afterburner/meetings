<x-app-layout :title="\Afterburner\Meetings\Support\PageHeader::make('Events', detail: $meeting->title)">
    <x-slot name="header">
        <x-afterburner-meetings::page-header section="Events" :detail="$meeting->title" />
    </x-slot>

    <div class="max-w-4xl mx-auto py-10 sm:px-6 lg:px-8">
        @livewire('meetings.show', ['team' => $team, 'meeting' => $meeting])
    </div>
</x-app-layout>
