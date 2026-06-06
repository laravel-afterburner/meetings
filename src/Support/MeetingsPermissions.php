<?php

namespace Afterburner\Meetings\Support;

use App\Models\Team;
use App\Models\User;
use App\Support\TeamPermissionGate;

/**
 * Meetings module areas (list vs calendar) mapped to permission slugs.
 */
final class MeetingsPermissions
{
    public const SECTION_MEETINGS = 'meetings';

    public const SECTION_CALENDAR = 'calendar';

    /**
     * @return array<string, string>
     */
    public static function sectionPermissionMap(): array
    {
        return [
            self::SECTION_MEETINGS => 'view_meetings_list',
            self::SECTION_CALENDAR => 'view_meetings_calendar',
        ];
    }

    /**
     * @return list<string>
     */
    public static function sectionDisplayOrder(): array
    {
        $sections = [self::SECTION_MEETINGS];

        if (config('afterburner-meetings.calendar.enabled', true)) {
            $sections[] = self::SECTION_CALENDAR;
        }

        return $sections;
    }

    /**
     * @return list<string>
     */
    public static function moduleAccessSlugs(): array
    {
        return [
            'view_meetings',
            'view_meetings_list',
            'view_meetings_calendar',
            'manage_meetings',
            'create_meetings',
            'edit_meetings',
            'delete_meetings',
            'conduct_meetings',
            'generate_meeting_notices',
            'save_meeting_minutes',
        ];
    }

    public static function canAccessModule(User $user, Team $team): bool
    {
        return TeamPermissionGate::allowsAny($user, $team->id, self::moduleAccessSlugs());
    }

    public static function canViewSection(User $user, Team $team, string $section): bool
    {
        $slug = self::sectionPermissionMap()[$section] ?? null;

        return $slug !== null
            && TeamPermissionGate::allows($user, $team->id, $slug);
    }

    /**
     * @return list<string>
     */
    public static function visibleSections(User $user, Team $team): array
    {
        $visible = [];

        foreach (self::sectionDisplayOrder() as $section) {
            if (self::canViewSection($user, $team, $section)) {
                $visible[] = $section;
            }
        }

        return $visible;
    }
}
