<?php

use Afterburner\Meetings\Http\Controllers\CalendarController;
use Afterburner\Meetings\Http\Controllers\MeetingsController;
use Afterburner\Meetings\Models\Meeting;
use Illuminate\Support\Facades\Route;


/*
| Calendar feed is fetched by external apps without a login session. Keep it
| outside the web middleware group so host apps do not run session/team
| middleware that expects Auth::user()->currentTeam.
*/
Route::get('/' . entity_url_slug() . '/{teamId}/meetings/calendar/feed.ics', [CalendarController::class, 'feed'])
    ->name('teams.meetings.calendar.feed')
    ->whereNumber('teamId');

Route::middleware(['web', 'auth', 'verified'])->group(function () {
    Route::get('/' . entity_url_slug() . '/{team}/meetings', [MeetingsController::class, 'index'])
        ->name('teams.meetings.index');

    Route::get('/' . entity_url_slug() . '/{team}/meetings/calendar', [CalendarController::class, 'index'])
        ->name('teams.meetings.calendar');

    Route::get('/' . entity_url_slug() . '/{team}/meetings/create', [MeetingsController::class, 'create'])
        ->name('teams.meetings.create')
        ->middleware('can:create,'.Meeting::class.',team');

    Route::get('/' . entity_url_slug() . '/{team}/meetings/{meeting}', [MeetingsController::class, 'show'])
        ->name('teams.meetings.show')
        ->whereNumber('meeting');

    Route::get('/' . entity_url_slug() . '/{team}/meetings/{meeting}/in-progress', [MeetingsController::class, 'inProgress'])
        ->name('teams.meetings.in-progress')
        ->whereNumber('meeting');

    Route::get('/' . entity_url_slug() . '/{team}/meetings/{meeting}/completed', [MeetingsController::class, 'completed'])
        ->name('teams.meetings.completed')
        ->whereNumber('meeting');

    Route::get('/' . entity_url_slug() . '/{team}/meetings/{meeting}/edit', [MeetingsController::class, 'edit'])
        ->name('teams.meetings.edit')
        ->middleware('can:update,meeting')
        ->whereNumber('meeting');
});
