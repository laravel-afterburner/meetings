<?php

namespace Afterburner\Meetings\Support;

class MeetingsPermissionDefinitions
{
    /**
     * @return array<int, array{name: string, slug: string, description: string}>
     */
    public static function all(): array
    {
        if (! class_exists(\App\Support\PermissionCatalog::class)) {
            return [
                [
                    'name' => 'Manage Meetings',
                    'slug' => 'manage_meetings',
                    'description' => 'Create and manage team meetings',
                ],
            ];
        }

        return collect(\App\Support\PermissionCatalog::definitions())
            ->filter(fn (array $permission) => in_array($permission['slug'], [
                'manage_meetings',
                'create_meetings',
                'edit_meetings',
                'delete_meetings',
                'conduct_meetings',
                'generate_meeting_notices',
                'save_meeting_minutes',
            ], true))
            ->values()
            ->all();
    }
}
