<?php

namespace Afterburner\Meetings\Support;

class MeetingsPermissionDefinitions
{
    /**
     * @return array<int, array{name: string, slug: string, description: string}>
     */
    public static function all(): array
    {
        return [
            [
                'name' => 'Manage Meetings',
                'slug' => 'manage_meetings',
                'description' => 'Create and manage team meetings',
            ],
        ];
    }
}
