<x-app-layout :title="\Afterburner\Meetings\Support\PageHeader::make('Meetings')">
    <x-slot name="header">
        <x-afterburner-meetings::page-header section="Meetings" />
    </x-slot>

    <div class="max-w-7xl mx-auto px-4 py-6 sm:py-10 sm:px-6 lg:px-8">
        @livewire('meetings.index', ['team' => $team])
    </div>
</x-app-layout>
