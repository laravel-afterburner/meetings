@props([
    'title',
    'description' => null,
])

<div {{ $attributes->merge(['class' => 'py-8 first:pt-0']) }}>
    <div class="md:grid md:grid-cols-3 md:gap-6">
        <div class="md:col-span-1">
            <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ $title }}</h3>
            @if ($description)
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ $description }}</p>
            @endif
        </div>
        <div class="md:col-span-2 space-y-6">
            {{ $slot }}
        </div>
    </div>
</div>
