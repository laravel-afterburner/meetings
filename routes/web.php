<?php

use Afterburner\Meetings\Http\Controllers\CalendarController;
use Afterburner\Meetings\Http\Controllers\MeetingsController;
use Afterburner\Meetings\Models\Meeting;
use Illuminate\Support\Facades\Route;

Route::middleware(['web'])->group(function () {
    Route::get('/teams/{team}/meetings/calendar/feed.ics', [CalendarController::class, 'feed'])
        ->name('teams.meetings.calendar.feed');
});

Route::middleware(['web', 'auth', 'verified'])->group(function () {
    Route::get('/teams/{team}/meetings', [MeetingsController::class, 'index'])
        ->name('teams.meetings.index');

    Route::get('/teams/{team}/meetings/calendar', [CalendarController::class, 'index'])
        ->name('teams.meetings.calendar');

    Route::get('/teams/{team}/meetings/create', [MeetingsController::class, 'create'])
        ->name('teams.meetings.create')
        ->middleware('can:create,'.Meeting::class.',team');

    Route::get('/teams/{team}/meetings/{meeting}', [MeetingsController::class, 'show'])
        ->name('teams.meetings.show')
        ->whereNumber('meeting');

    Route::get('/teams/{team}/meetings/{meeting}/edit', [MeetingsController::class, 'edit'])
        ->name('teams.meetings.edit')
        ->middleware('can:update,meeting')
        ->whereNumber('meeting');
});
