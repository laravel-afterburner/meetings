<?php

namespace Afterburner\Meetings\Support;

class MinutesTemplate
{
    /**
     * @return array<string, array{label: string, enabled: bool}>
     */
    public function sections(): array
    {
        $sections = config('afterburner-meetings.minutes_template.sections', []);

        return collect($sections)
            ->filter(fn (array $section) => ($section['enabled'] ?? true) === true)
            ->all();
    }

    public function isEnabled(string $key): bool
    {
        return array_key_exists($key, $this->sections());
    }

    public function label(string $key): string
    {
        return $this->sections()[$key]['label'] ?? ucfirst(str_replace('_', ' ', $key));
    }
}
