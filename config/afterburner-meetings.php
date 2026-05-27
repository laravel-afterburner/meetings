<?php

return [

    'enabled' => env('AFTERBURNER_MEETINGS_ENABLED', true),

    'documents_enabled' => env('AFTERBURNER_MEETINGS_DOCUMENTS_ENABLED', true),

    'voting_enabled' => env('AFTERBURNER_MEETINGS_VOTING_ENABLED', true),

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

    'audit' => [
        'skip_routes' => [],
    ],

];
