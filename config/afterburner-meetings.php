<?php

use Afterburner\Meetings\Support\DefaultMeetingMinutesAttendanceSummaryProvider;

return [

    'enabled' => true,

    'calendar' => [
        'enabled' => true,

        /*
        | When true, calendar feed URLs always use https:// even if APP_URL is http://.
        | By default, http is upgraded to https only when the page request is secure.
        */
        'feed_force_https' => false,
    ],

    'documents_enabled' => true,

    /*
    |--------------------------------------------------------------------------
    | Meeting document package
    |--------------------------------------------------------------------------
    |
    | Completed meetings can be compiled into a PDF and stored in a protected
    | root folder in the documents library (similar to host-managed folders).
    |
    */
    'documents_package' => [
        'folder_name' => 'Meetings',
    ],

    'voting_enabled' => true,

    /*
    |--------------------------------------------------------------------------
    | Meeting audience
    |--------------------------------------------------------------------------
    |
    | Role slugs selected when creating a meeting determine who is invited.
    | Host apps can override defaults for council vs owner audiences.
    |
    */
    'default_target_roles_by_type' => [
        'agm' => [],
        'council' => [],
        'special' => [],
    ],

    'selectable_audience_role_slugs' => null,

    /*
    |--------------------------------------------------------------------------
    | Council positions for action-item assignment
    |--------------------------------------------------------------------------
    |
    | Members holding these role slugs may be assigned action items even when
    | they were not marked present at the meeting.
    |
    */
    'council_position_role_slugs' => [
        'president',
        'vice_president',
        'secretary',
        'treasurer',
    ],

    /*
    |--------------------------------------------------------------------------
    | Attendance recorder fallback chain
    |--------------------------------------------------------------------------
    |
    | The first present person in this chain may record attendance and minutes.
    | Use "organizer" for the meeting creator when no role holder is present.
    |
    */
    'attendance_recorder_chain' => [
        'secretary',
        'president',
        'vice_president',
        'organizer',
    ],

    /*
    |--------------------------------------------------------------------------
    | Minutes template sections
    |--------------------------------------------------------------------------
    |
    | Reusable sections that can be merged into an editable minutes draft.
    | Host apps can override labels or disable sections in published config.
    |
    */
    'minutes_template' => [
        'sections' => [
            'attendance_summary' => [
                'label' => 'Attendance summary',
                'enabled' => true,
            ],
            'quorum' => [
                'label' => 'Quorum',
                'enabled' => true,
            ],
            'resolutions' => [
                'label' => 'Resolutions',
                'enabled' => true,
            ],
            'action_items' => [
                'label' => 'Action items',
                'enabled' => true,
            ],
            'agenda' => [
                'label' => 'Agenda',
                'enabled' => true,
            ],
        ],
    ],

    'minutes_attendance_summary_provider' => DefaultMeetingMinutesAttendanceSummaryProvider::class,

    /*
    |--------------------------------------------------------------------------
    | Agenda reference providers
    |--------------------------------------------------------------------------
    |
    | Host apps register MeetingReferenceProvider classes here so meetings
    | can link agenda items to existing records (maintenance issues, etc.).
    |
    */
    'reference_providers' => [
        //
    ],

    'audit' => [
        'skip_routes' => [
            'teams.meetings.calendar.feed',
        ],
    ],

];
