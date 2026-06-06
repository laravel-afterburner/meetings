<?php

namespace Afterburner\Meetings\Livewire\Meetings;

use Afterburner\Meetings\Actions\CreateCalendarEvent;
use Afterburner\Meetings\Actions\DeleteCalendarEvent;
use Afterburner\Meetings\Actions\UpdateCalendarEvent;
use Afterburner\Meetings\Models\CalendarEvent;
use Afterburner\Meetings\Models\Meeting;
use Afterburner\Meetings\Support\CalendarEntry;
use Afterburner\Meetings\Support\CalendarEventSchedule;
use Afterburner\Meetings\Support\CalendarFeedToken;
use Afterburner\Meetings\Support\CalendarQuery;
use Afterburner\Meetings\Support\CalendarWeekLayout;
use App\Support\TeamDateTime;
use App\Models\Team;
use App\Traits\InteractsWithBanner;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class Calendar extends Component
{
    use InteractsWithBanner;

    public int $teamId;

    public string $month = '';

    public bool $showEventModal = false;

    public bool $showEntryModal = false;

    public ?string $viewingEntryKind = null;

    public ?int $viewingEntryId = null;

    public bool $viewingCanEdit = false;

    public string $viewTitle = '';

    public string $viewDescription = '';

    public string $viewLocation = '';

    public string $viewScheduleLabel = '';

    public ?string $viewStatusLabel = null;

    public ?string $viewTypeLabel = null;

    public ?string $viewVirtualLink = null;

    public ?string $viewEntryUrl = null;

    public ?string $viewEditUrl = null;

    public ?int $editingEventId = null;

    public string $title = '';

    public string $description = '';

    public string $location = '';

    public bool $allDay = false;

    public string $startDate = '';

    public string $endDate = '';

    public ?string $startsAtLocal = null;

    public ?string $endsAtLocal = null;

    public string $displayTimezoneMode = '';

    protected $queryString = [
        'month' => ['except' => ''],
        'displayTimezoneMode' => ['except' => '', 'as' => 'tz'],
    ];

    public function mount(Team $team): void
    {
        if (! Auth::user()->belongsToTeam($team)) {
            abort(403, 'Access denied.');
        }

        abort_unless(config('afterburner-meetings.calendar.enabled', true), 404);

        $user = Auth::user();

        abort_unless(
            \Afterburner\Meetings\Support\MeetingsPermissions::canViewSection($user, $team, \Afterburner\Meetings\Support\MeetingsPermissions::SECTION_CALENDAR),
            403
        );

        $this->teamId = $team->id;
        $this->resolveDisplayTimezoneMode($team);

        if ($this->month === '') {
            $this->month = $this->nowInCalendarTimezone($team)->format('Y-m');
        }
    }

    public function setDisplayTimezoneMode(string $mode): void
    {
        $team = Team::query()->findOrFail($this->teamId);

        if (! in_array($mode, [TeamDateTime::CALENDAR_DISPLAY_TEAM, TeamDateTime::CALENDAR_DISPLAY_USER], true)) {
            return;
        }

        if ($mode === TeamDateTime::CALENDAR_DISPLAY_USER && ! TeamDateTime::canChooseCalendarDisplayTimezone($team)) {
            return;
        }

        $this->displayTimezoneMode = $mode;
        session(['meetings.calendar.display_timezone_mode' => $mode]);
    }

    public function previousMonth(): void
    {
        $this->setMonth($this->monthCarbon()->copy()->subMonth()->format('Y-m'));
    }

    public function nextMonth(): void
    {
        $this->setMonth($this->monthCarbon()->copy()->addMonth()->format('Y-m'));
    }

    public function goToToday(): void
    {
        $team = Team::query()->findOrFail($this->teamId);
        $this->setMonth($this->nowInCalendarTimezone($team)->format('Y-m'));
    }

    protected function setMonth(string $month): void
    {
        $this->month = $month;
        $this->dispatch('calendar-scroll-to-month-start');
    }

    public function openCreateForDate(string $date): void
    {
        abort_unless(Auth::user()->can('create', [CalendarEvent::class, Team::query()->findOrFail($this->teamId)]), 403);

        $this->resetEventForm();
        $this->startDate = $date;
        $this->endDate = $date;
        $this->startsAtLocal = $date.'T09:00';
        $this->endsAtLocal = $date.'T10:00';
        $this->showEventModal = true;
    }

    public function openCreateForRange(string $startDate, string $endDate): void
    {
        abort_unless(Auth::user()->can('create', [CalendarEvent::class, Team::query()->findOrFail($this->teamId)]), 403);

        if ($startDate > $endDate) {
            [$startDate, $endDate] = [$endDate, $startDate];
        }

        $this->resetEventForm();
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->startsAtLocal = $startDate.'T09:00';
        $this->endsAtLocal = $endDate.'T10:00';
        $this->allDay = $startDate !== $endDate;
        $this->showEventModal = true;
    }

    public function updatedStartDate(string $value): void
    {
        if (! $this->allDay) {
            return;
        }

        $this->endDate = CalendarEventSchedule::syncedAllDayEnd($value, $this->endDate);
    }

    public function updatedEndDate(string $value): void
    {
        if (! $this->allDay) {
            return;
        }

        if ($this->startDate === '' || $value < $this->startDate) {
            $this->startDate = $value;
        }
    }

    public function updatedStartsAtLocal(?string $value): void
    {
        if ($this->allDay || blank($value)) {
            return;
        }

        $team = Team::query()->findOrFail($this->teamId);
        $this->endsAtLocal = CalendarEventSchedule::syncedTimedEnd($team, $value, $this->endsAtLocal);
    }

    public function updatedAllDay(bool $allDay): void
    {
        if ($allDay) {
            if (filled($this->startsAtLocal)) {
                $this->startDate = substr($this->startsAtLocal, 0, 10);
            }

            if (filled($this->endsAtLocal)) {
                $this->endDate = substr($this->endsAtLocal, 0, 10);
            }

            return;
        }

        if (filled($this->startDate)) {
            $this->startsAtLocal = $this->startDate.'T09:00';
        }

        $team = Team::query()->findOrFail($this->teamId);

        if (filled($this->startsAtLocal)) {
            $this->endsAtLocal = CalendarEventSchedule::syncedTimedEnd(
                $team,
                $this->startsAtLocal,
                filled($this->endsAtLocal) ? $this->endsAtLocal : $this->startDate.'T10:00',
            );
        }
    }

    public function openShowEvent(int $eventId): void
    {
        $event = CalendarEvent::query()
            ->where('team_id', $this->teamId)
            ->findOrFail($eventId);

        abort_unless(Auth::user()->can('view', $event), 403);

        $team = Team::query()->findOrFail($this->teamId);
        $this->populateViewFromEvent($event, $team);
        $this->showEntryModal = true;
    }

    public function openShowMeeting(int $meetingId): void
    {
        $meeting = Meeting::query()
            ->where('team_id', $this->teamId)
            ->findOrFail($meetingId);

        abort_unless(Auth::user()->can('view', $meeting), 403);

        $team = Team::query()->findOrFail($this->teamId);
        $this->populateViewFromMeeting($meeting, $team);
        $this->showEntryModal = true;
    }

    public function closeEntryModal(): void
    {
        $this->showEntryModal = false;
        $this->resetViewState();
    }

    public function editEventFromView(): void
    {
        if ($this->viewingEntryKind !== 'event' || ! $this->viewingEntryId) {
            return;
        }

        $eventId = $this->viewingEntryId;
        $this->closeEntryModal();
        $this->openEditEvent($eventId);
    }

    public function openEditEvent(int $eventId): void
    {
        $event = CalendarEvent::query()
            ->where('team_id', $this->teamId)
            ->findOrFail($eventId);

        abort_unless(Auth::user()->can('update', $event), 403);

        $team = Team::query()->findOrFail($this->teamId);
        $startsAt = TeamDateTime::toTeamTimezone($team, $event->starts_at);
        $endsAt = TeamDateTime::toTeamTimezone($team, $event->ends_at);

        $this->editingEventId = $event->id;
        $this->title = $event->title;
        $this->description = $event->description ?? '';
        $this->location = $event->location ?? '';
        $this->allDay = $event->all_day;
        $this->startDate = $startsAt->format('Y-m-d');
        $this->endDate = $endsAt->format('Y-m-d');
        $this->startsAtLocal = TeamDateTime::toDateTimeLocal($team, $event->starts_at);
        $this->endsAtLocal = TeamDateTime::toDateTimeLocal($team, $event->ends_at);
        $this->showEventModal = true;
    }

    public function closeEventModal(): void
    {
        $this->showEventModal = false;
        $this->resetEventForm();
    }

    public function saveEvent(): void
    {
        $team = Team::query()->findOrFail($this->teamId);

        $rules = [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:5000',
            'location' => 'nullable|string|max:255',
        ];

        if ($this->allDay) {
            $rules['startDate'] = 'required|date';
            $rules['endDate'] = 'required|date|after_or_equal:startDate';
        } else {
            $rules['startsAtLocal'] = 'required|date';
            $rules['endsAtLocal'] = 'required|date|after:startsAtLocal';
        }

        $this->validate($rules, [
            'endsAtLocal.after' => 'The end must be after the start.',
            'endDate.after_or_equal' => 'The end date must be on or after the start date.',
        ]);

        [$startsAt, $endsAt] = $this->resolveEventTimes($team);

        if ($this->editingEventId) {
            $event = CalendarEvent::query()
                ->where('team_id', $this->teamId)
                ->findOrFail($this->editingEventId);

            app(UpdateCalendarEvent::class)->execute(
                $event,
                Auth::user(),
                $this->title,
                $startsAt,
                $endsAt,
                $this->allDay,
                $this->description ?: null,
                $this->location ?: null,
            );

            $this->banner('Event updated.');
        } else {
            app(CreateCalendarEvent::class)->execute(
                $team,
                Auth::user(),
                $this->title,
                $startsAt,
                $endsAt,
                $this->allDay,
                $this->description ?: null,
                $this->location ?: null,
            );

            $this->banner('Event added to the calendar.');
        }

        $this->closeEventModal();
    }

    public function deleteEvent(): void
    {
        if (! $this->editingEventId) {
            return;
        }

        $event = CalendarEvent::query()
            ->where('team_id', $this->teamId)
            ->findOrFail($this->editingEventId);

        app(DeleteCalendarEvent::class)->execute($event, Auth::user());

        $this->banner('Event deleted.');
        $this->closeEventModal();
    }

    public function copyFeedUrl(): void
    {
        $team = Team::query()->findOrFail($this->teamId);
        $url = CalendarFeedToken::feedUrl(Auth::user(), $team);
        $this->js('window.navigator.clipboard.writeText('.json_encode($url).')');
        $this->banner('Calendar feed URL copied.');
    }

    public function render()
    {
        $team = Team::query()->findOrFail($this->teamId);
        $displayTimezone = $this->calendarTimezone($team);
        $monthStart = $this->monthCarbon()->copy()->startOfMonth();
        $monthEnd = $this->monthCarbon()->copy()->endOfMonth();
        $gridStart = $monthStart->copy()->startOfWeek(Carbon::SUNDAY);
        $gridEnd = $monthEnd->copy()->endOfWeek(Carbon::SATURDAY);

        /** @var Collection<int, CalendarEntry> $entries */
        $entries = app(CalendarQuery::class)->entriesForRange($team, $gridStart, $gridEnd)
            ->map(fn (CalendarEntry $entry) => $entry->forDisplayTimezone($displayTimezone));

        $weeks = collect();
        $cursor = $gridStart->copy();
        $today = $this->nowInCalendarTimezone($team);

        while ($cursor->lte($gridEnd)) {
            $weekDays = collect();

            for ($day = 0; $day < 7; $day++) {
                $date = $cursor->copy();

                $weekDays->push([
                    'date' => $date->format('Y-m-d'),
                    'day' => $date->day,
                    'inMonth' => $date->month === $monthStart->month,
                    'isToday' => $date->isSameDay($today),
                ]);

                $cursor->addDay();
            }

            $weekLayout = app(CalendarWeekLayout::class)->layout($weekDays, $entries);
            $weeks->push($weekLayout);
        }

        return view('afterburner-meetings::meetings.livewire.calendar', [
            'team' => $team,
            'weeks' => $weeks,
            'monthLabel' => $monthStart->format('F Y'),
            'timezone' => $displayTimezone,
            'inputTimezone' => TeamDateTime::datetimeLocalTimezone($team),
            'inputTeamTimezoneHint' => TeamDateTime::datetimeLocalTeamTimezoneHint($team),
            'canChooseDisplayTimezone' => TeamDateTime::canChooseCalendarDisplayTimezone($team),
            'displayTimezoneMode' => $this->displayTimezoneMode,
            'canCreate' => Auth::user()->can('create', [CalendarEvent::class, $team]),
            'todayDate' => $today->format('Y-m-d'),
            'webcalUrl' => CalendarFeedToken::webcalUrl(Auth::user(), $team),
            'monthAnchorDate' => $monthStart->format('Y-m-d'),
        ]);
    }

    protected function resolveDisplayTimezoneMode(Team $team): void
    {
        $validModes = [TeamDateTime::CALENDAR_DISPLAY_TEAM, TeamDateTime::CALENDAR_DISPLAY_USER];

        if (! in_array($this->displayTimezoneMode, $validModes, true)) {
            $this->displayTimezoneMode = session(
                'meetings.calendar.display_timezone_mode',
                TeamDateTime::defaultCalendarDisplayMode($team)
            );
        }

        session(['meetings.calendar.display_timezone_mode' => $this->displayTimezoneMode]);
    }

    protected function calendarTimezone(Team $team): string
    {
        return TeamDateTime::resolveCalendarDisplayTimezone($team, $this->displayTimezoneMode);
    }

    protected function nowInCalendarTimezone(Team $team): Carbon
    {
        return now()->setTimezone($this->calendarTimezone($team));
    }

    protected function monthCarbon(): Carbon
    {
        $team = Team::query()->findOrFail($this->teamId);
        $timezone = $this->calendarTimezone($team);

        if (! preg_match('/^\d{4}-\d{2}$/', $this->month)) {
            return $this->nowInCalendarTimezone($team)->startOfMonth();
        }

        // Y-m alone is ambiguous in Carbon with a timezone (e.g. 2026-06 becomes July 1 in America/Vancouver).
        $month = Carbon::createFromFormat('Y-m-d', $this->month.'-01', $timezone);

        if ($month === false) {
            return $this->nowInCalendarTimezone($team)->startOfMonth();
        }

        return $month->startOfDay();
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    protected function resolveEventTimes(Team $team): array
    {
        if ($this->allDay) {
            $timezone = TeamDateTime::teamTimezone($team);
            $startsAt = Carbon::parse($this->startDate, $timezone)->startOfDay()->utc();
            $endsAt = Carbon::parse($this->endDate, $timezone)->endOfDay()->utc();

            return [$startsAt, $endsAt];
        }

        $startsAt = TeamDateTime::fromDateTimeLocal($team, $this->startsAtLocal)?->utc();
        $endsAt = TeamDateTime::fromDateTimeLocal($team, $this->endsAtLocal)?->utc();

        if (! $startsAt || ! $endsAt) {
            throw ValidationException::withMessages([
                'startsAtLocal' => 'Enter a valid start date and time.',
            ]);
        }

        if ($endsAt->lte($startsAt)) {
            throw ValidationException::withMessages([
                'endsAtLocal' => 'The end must be after the start.',
            ]);
        }

        return [$startsAt, $endsAt];
    }

    protected function resetEventForm(): void
    {
        $this->editingEventId = null;
        $this->title = '';
        $this->description = '';
        $this->location = '';
        $this->allDay = false;
        $this->startDate = '';
        $this->endDate = '';
        $this->startsAtLocal = null;
        $this->endsAtLocal = null;
        $this->resetValidation();
    }

    protected function populateViewFromEvent(CalendarEvent $event, Team $team): void
    {
        $displayTimezone = $this->calendarTimezone($team);
        $startsAt = TeamDateTime::toTeamTimezone($team, $event->starts_at)->setTimezone($displayTimezone);
        $endsAt = TeamDateTime::toTeamTimezone($team, $event->ends_at)->setTimezone($displayTimezone);

        $this->resetViewState();
        $this->viewingEntryKind = 'event';
        $this->viewingEntryId = $event->id;
        $this->viewingCanEdit = Auth::user()->can('update', $event);
        $this->viewTitle = $event->title;
        $this->viewDescription = $event->description ?? '';
        $this->viewLocation = $event->location ?? '';
        $this->viewScheduleLabel = TeamDateTime::formatCalendarEntrySchedule($startsAt, $endsAt, $event->all_day);
        $this->viewTypeLabel = 'Calendar event';
    }

    protected function populateViewFromMeeting(Meeting $meeting, Team $team): void
    {
        $displayTimezone = $this->calendarTimezone($team);
        $startsAt = TeamDateTime::toTeamTimezone($team, $meeting->scheduled_at)->setTimezone($displayTimezone);

        $this->resetViewState();
        $this->viewingEntryKind = 'meeting';
        $this->viewingEntryId = $meeting->id;
        $this->viewingCanEdit = Auth::user()->can('update', $meeting);
        $this->viewTitle = $meeting->title;
        $this->viewDescription = $meeting->agenda_notes ?? '';
        $this->viewLocation = $meeting->location ?? '';
        $this->viewVirtualLink = $meeting->virtual_link;
        $this->viewScheduleLabel = TeamDateTime::formatDisplayCarbon($startsAt);
        $this->viewStatusLabel = $meeting->status->label();
        $this->viewTypeLabel = $meeting->type->label();
        $this->viewEntryUrl = route('teams.meetings.show', ['team' => $team->id, 'meeting' => $meeting->id]);
        $this->viewEditUrl = route('teams.meetings.edit', ['team' => $team->id, 'meeting' => $meeting->id]);
    }

    protected function resetViewState(): void
    {
        $this->viewingEntryKind = null;
        $this->viewingEntryId = null;
        $this->viewingCanEdit = false;
        $this->viewTitle = '';
        $this->viewDescription = '';
        $this->viewLocation = '';
        $this->viewScheduleLabel = '';
        $this->viewStatusLabel = null;
        $this->viewTypeLabel = null;
        $this->viewVirtualLink = null;
        $this->viewEntryUrl = null;
        $this->viewEditUrl = null;
    }
}
