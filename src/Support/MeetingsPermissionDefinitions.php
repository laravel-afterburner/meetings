<?php

namespace Afterburner\Meetings\Support;

class MeetingsPermissionDefinitions
{
    /**
     * @return list<string>
     */
    public static function slugs(): array
    {
        return [
            'manage_meetings',
            'view_meetings',
            'view_meetings_list',
            'view_meetings_calendar',
            'create_meetings',
            'edit_meetings',
            'delete_meetings',
            'conduct_meetings',
            'generate_meeting_notices',
            'save_meeting_minutes',
        ];
    }

    /**
     * @return array<int, array{name: string, slug: string, description: string}>
     */
    public static function all(): array
    {
        if (class_exists(\App\Support\PermissionCatalog::class)) {
            return collect(\App\Support\PermissionCatalog::definitions())
                ->filter(fn (array $permission) => in_array($permission['slug'], self::slugs(), true))
                ->values()
                ->all();
        }

        return [
            [
                'name' => 'View Meetings',
                'slug' => 'view_meetings',
                'description' => 'View meetings and calendar',
            ],
            [
                'name' => 'Manage Meetings',
                'slug' => 'manage_meetings',
                'description' => 'Create and manage team meetings',
            ],
            [
                'name' => 'Create Meetings',
                'slug' => 'create_meetings',
                'description' => 'Schedule new meetings',
            ],
            [
                'name' => 'Edit Meetings',
                'slug' => 'edit_meetings',
                'description' => 'Edit meeting details and agendas',
            ],
            [
                'name' => 'Delete Meetings',
                'slug' => 'delete_meetings',
                'description' => 'Delete or cancel meetings',
            ],
            [
                'name' => 'Conduct Meetings',
                'slug' => 'conduct_meetings',
                'description' => 'Start, run, and complete meetings',
            ],
            [
                'name' => 'Generate Meeting Notices',
                'slug' => 'generate_meeting_notices',
                'description' => 'Generate printable meeting notices',
            ],
            [
                'name' => 'Save Meeting Minutes',
                'slug' => 'save_meeting_minutes',
                'description' => 'Export finalized minutes as documents',
            ],
        ];
    }
}
