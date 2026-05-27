<x-app-layout :title="$meeting ?? null ? 'Edit Meeting' : 'Create Meeting'">
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
            {{ isset($meeting) ? 'Edit Meeting' : 'Create Meeting' }}
        </h2>
    </x-slot>

    <div class="max-w-4xl mx-auto py-10 sm:px-6 lg:px-8">
        @livewire('meetings.create', array_filter([
            'team' => $team,
            'meetingId' => isset($meeting) ? $meeting->id : null,
        ], fn ($value) => $value !== null))
    </div>
</x-app-layout>
