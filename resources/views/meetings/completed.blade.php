<x-app-layout :title="\Afterburner\Meetings\Support\PageHeader::make('Meeting', 'Wrap up', $meeting->title)">
    <x-slot name="header">
        <x-afterburner-meetings::page-header section="Meeting" action="Wrap up" :detail="$meeting->title" />
    </x-slot>

    <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8">
        @livewire('meetings.completed', ['team' => $team, 'meeting' => $meeting])
    </div>
</x-app-layout>
