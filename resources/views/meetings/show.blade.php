<x-app-layout title="Meeting">
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
            {{ $meeting->title }}
        </h2>
    </x-slot>

    <div class="max-w-4xl mx-auto py-10 sm:px-6 lg:px-8">
        @livewire('meetings.show', ['team' => $team, 'meeting' => $meeting])
    </div>
</x-app-layout>
