<?php

namespace Afterburner\Meetings\Livewire\Meetings;

use Afterburner\Meetings\Actions\CreateCalendarEvent;
use Afterburner\Meetings\Actions\DeleteCalendarEvent;
use Afterburner\Meetings\Actions\UpdateCalendarEvent;
use Afterburner\Meetings\Models\CalendarEvent;
use Afterburner\Meetings\Support\CalendarEventSchedule;
use Afterburner\Meetings\Support\CalendarEntry;
use Afterburner\Meetings\Support\CalendarFeedToken;
use Afterburner\Meetings\Support\CalendarQuery;
use Afterburner\Meetings\Support\CalendarWeekLayout;
use Afterburner\Meetings\Support\TeamDateTime;
use App\Models\Team;
use App\Traits\InteractsWithBanner;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Calendar extends Component
{
    use InteractsWithBanner;

    public int $teamId;

    public string $month = '';

    public bool $showEventModal = false;

    public ?int $editingEventId = null;

    public string $title = '';

    public string $description = '';

    public string $location = '';

    public bool $allDay = false;

    public string $startDate = '';

    public string $endDate = '';

    public ?string $startsAtLocal = null;

    public ?string $endsAtLocal = null;

    protected $queryString = [
        'month' => ['except' => ''],
    ];

    public function mount(Team $team): void
    {
        if (! Auth::user()->belongsToTeam($team)) {
            abort(403, 'Access denied.');
        }

        abort_unless(config('afterburner-meetings.calendar.enabled', true), 404);

        abort_unless(Auth::user()->can('viewAny', CalendarEvent::class), 403);

        $this->teamId = $team->id;

        if ($this->month === '') {
            $this->month = TeamDateTime::toTeamTimezone($team, now())->format('Y-m');
        }
    }

    public function previousMonth(): void
    {
        $this->month = $this->monthCarbon()->subMonth()->format('Y-m');
    }

    public function nextMonth(): void
    {
        $this->month = $this->monthCarbon()->addMonth()->format('Y-m');
    }

    public function goToToday(): void
    {
        $team = Team::query()->findOrFail($this->teamId);
        $this->month = TeamDateTime::toTeamTimezone($team, now())->format('Y-m');
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

    public function viewMeeting(int $meetingId)
    {
        return $this->redirectRoute('teams.meetings.show', [
            'team' => $this->teamId,
            'meeting' => $meetingId,
        ]);
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
        $monthStart = $this->monthCarbon()->copy()->startOfMonth();
        $monthEnd = $this->monthCarbon()->copy()->endOfMonth();
        $gridStart = $monthStart->copy()->startOfWeek(Carbon::SUNDAY);
        $gridEnd = $monthEnd->copy()->endOfWeek(Carbon::SATURDAY);

        /** @var Collection<int, CalendarEntry> $entries */
        $entries = app(CalendarQuery::class)->entriesForRange($team, $gridStart, $gridEnd);

        $weeks = collect();
        $cursor = $gridStart->copy();

        while ($cursor->lte($gridEnd)) {
            $weekDays = collect();

            for ($day = 0; $day < 7; $day++) {
                $date = $cursor->copy();

                $weekDays->push([
                    'date' => $date->format('Y-m-d'),
                    'day' => $date->day,
                    'inMonth' => $date->month === $monthStart->month,
                    'isToday' => $date->isSameDay(TeamDateTime::toTeamTimezone($team, now())),
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
            'timezone' => TeamDateTime::teamTimezone($team),
            'canCreate' => Auth::user()->can('create', [CalendarEvent::class, $team]),
            'todayDate' => TeamDateTime::toTeamTimezone($team, now())->format('Y-m-d'),
            'feedUrl' => CalendarFeedToken::feedUrl(Auth::user(), $team),
            'webcalUrl' => CalendarFeedToken::webcalUrl(Auth::user(), $team),
        ]);
    }

    protected function monthCarbon(): Carbon
    {
        $team = Team::query()->findOrFail($this->teamId);
        $timezone = TeamDateTime::teamTimezone($team);

        return Carbon::createFromFormat('Y-m', $this->month, $timezone)->startOfMonth();
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    protected function resolveEventTimes(Team $team): array
    {
        $timezone = TeamDateTime::teamTimezone($team);

        if ($this->allDay) {
            $startsAt = Carbon::parse($this->startDate, $timezone)->startOfDay()->utc();
            $endsAt = Carbon::parse($this->endDate, $timezone)->endOfDay()->utc();

            return [$startsAt, $endsAt];
        }

        $startsAt = TeamDateTime::fromDateTimeLocal($team, $this->startsAtLocal)?->utc();
        $endsAt = TeamDateTime::fromDateTimeLocal($team, $this->endsAtLocal)?->utc();

        if (! $startsAt || ! $endsAt) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'startsAtLocal' => 'Enter a valid start date and time.',
            ]);
        }

        if ($endsAt->lte($startsAt)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
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
}
