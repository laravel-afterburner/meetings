<x-app-layout :title="\App\Support\PageHeader::make('Meeting', 'In progress', $meeting->title)">
    <x-slot name="header">
        <x-afterburner-meetings::page-header section="Meeting" action="In progress" :detail="$meeting->title" />
    </x-slot>

    <div class="max-w-4xl mx-auto px-4 py-6 sm:py-10 sm:px-6 lg:px-8">
        @livewire('meetings.in-progress', ['team' => $team, 'meeting' => $meeting])
    </div>
</x-app-layout>
