<?php

namespace Afterburner\Meetings\Providers;

use Afterburner\Documents\Models\Document;
use Afterburner\Documents\Models\Folder;
use Afterburner\Meetings\Console\Commands\InstallCommand;
use Afterburner\Meetings\Contracts\MeetingMinutesAttendanceSummaryProvider;
use Afterburner\Meetings\Database\Seeders\MeetingsPermissionsSeeder;
use Afterburner\Meetings\Events\MeetingActionItemAssigned;
use Afterburner\Meetings\Listeners\NotifyMeetingActionItemAssignee;
use Afterburner\Meetings\Listeners\SyncMeetingBallotContext;
use Afterburner\Meetings\Livewire\Documents\DocumentMeetingLinks;
use Afterburner\Meetings\Livewire\Meetings\Calendar;
use Afterburner\Meetings\Livewire\Meetings\Completed;
use Afterburner\Meetings\Livewire\Meetings\Create;
use Afterburner\Meetings\Livewire\Meetings\Index;
use Afterburner\Meetings\Livewire\Meetings\InProgress;
use Afterburner\Meetings\Livewire\Meetings\MeetingActionItems;
use Afterburner\Meetings\Livewire\Meetings\MeetingAgendaItems;
use Afterburner\Meetings\Livewire\Meetings\MeetingAttendance;
use Afterburner\Meetings\Livewire\Meetings\MeetingBallots;
use Afterburner\Meetings\Livewire\Meetings\MeetingDocuments;
use Afterburner\Meetings\Livewire\Meetings\MeetingMinutes;
use Afterburner\Meetings\Livewire\Meetings\Show;
use Afterburner\Meetings\Models\CalendarEvent;
use Afterburner\Meetings\Models\Meeting;
use Afterburner\Meetings\Models\MeetingActionItem;
use Afterburner\Meetings\Models\MeetingAgendaItem;
use Afterburner\Meetings\Policies\CalendarEventPolicy;
use Afterburner\Meetings\Policies\MeetingActionItemPolicy;
use Afterburner\Meetings\Policies\MeetingAgendaItemPolicy;
use Afterburner\Meetings\Policies\MeetingPolicy;
use Afterburner\Meetings\Support\DefaultMeetingMinutesAttendanceSummaryProvider;
use Afterburner\Meetings\Support\DocumentsIntegration;
use Afterburner\Meetings\Support\MeetingCompiledDocumentGuard;
use Afterburner\Meetings\Support\MeetingReferenceRegistry;
use Afterburner\Meetings\Support\MeetingsDocumentFolder;
use Afterburner\Meetings\Support\VotingIntegration;
use Afterburner\Playbook\Support\Playbook;
use Afterburner\Voting\Events\BallotClosed;
use Afterburner\Voting\Events\BallotPublished;
use App\Models\Team;
use App\Support\Navigation;
use App\Support\PackageSeederRegistry;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class MeetingsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if (! class_exists(Team::class)) {
            return;
        }

        $this->mergeConfigFrom(
            __DIR__.'/../../config/afterburner-meetings.php',
            'afterburner-meetings'
        );

        $this->app->singleton(
            MeetingMinutesAttendanceSummaryProvider::class,
            fn ($app) => $app->make(config(
                'afterburner-meetings.minutes_attendance_summary_provider',
                DefaultMeetingMinutesAttendanceSummaryProvider::class
            ))
        );

        $this->app->singleton(MeetingReferenceRegistry::class);
    }

    public function boot(): void
    {
        if (! class_exists(Team::class)) {
            return;
        }

        if (! config('afterburner-meetings.enabled', true)) {
            return;
        }

        $this->publishes([
            __DIR__.'/../../config/afterburner-meetings.php' => config_path('afterburner-meetings.php'),
        ], 'afterburner-meetings-config');

        $this->publishes([
            __DIR__.'/../../database/migrations' => database_path('migrations'),
        ], 'afterburner-meetings-migrations');

        $this->publishes([
            __DIR__.'/../../resources/views' => resource_path('views/vendor/afterburner-meetings'),
        ], 'afterburner-meetings-assets');

        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'afterburner-meetings');
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        $this->loadRoutesFrom(__DIR__.'/../../routes/web.php');

        $this->registerLivewireComponents();
        $this->registerPolicies();
        $this->registerMeetingReferenceProviders();
        $this->registerAuditSkipRoutes();
        $this->registerNavigation();
        $this->registerPlaybook();
        $this->registerVotingEventListeners();
        $this->registerActionItemEventListeners();
        $this->app->booted(fn () => $this->registerDocumentGuards());

        $this->registerPackageSeeder();

        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,
            ]);
        }
    }

    protected function registerLivewireComponents(): void
    {
        Livewire::component('meetings.index', Index::class);
        Livewire::component('meetings.calendar', Calendar::class);
        Livewire::component('meetings.show', Show::class);
        Livewire::component('meetings.in-progress', InProgress::class);
        Livewire::component('meetings.completed', Completed::class);
        Livewire::component('meetings.create', Create::class);
        Livewire::component('meetings.meeting-action-items', MeetingActionItems::class);
        Livewire::component('meetings.meeting-agenda-items', MeetingAgendaItems::class);
        Livewire::component('meetings.meeting-attendance', MeetingAttendance::class);
        Livewire::component('meetings.meeting-minutes', MeetingMinutes::class);

        if (DocumentsIntegration::isAvailable()) {
            Livewire::component('meetings.meeting-documents', MeetingDocuments::class);
            Livewire::component('meetings.document-meeting-links', DocumentMeetingLinks::class);
        }

        if (VotingIntegration::isAvailable()) {
            Livewire::component('meetings.meeting-ballots', MeetingBallots::class);
        }
    }

    protected function registerPolicies(): void
    {
        Gate::policy(Meeting::class, MeetingPolicy::class);
        Gate::policy(CalendarEvent::class, CalendarEventPolicy::class);
        Gate::policy(MeetingActionItem::class, MeetingActionItemPolicy::class);
        Gate::policy(MeetingAgendaItem::class, MeetingAgendaItemPolicy::class);
    }

    protected function registerMeetingReferenceProviders(): void
    {
        $registry = $this->app->make(MeetingReferenceRegistry::class);

        foreach (config('afterburner-meetings.reference_providers', []) as $providerClass) {
            if (! is_string($providerClass) || ! class_exists($providerClass)) {
                continue;
            }

            $registry->register($this->app->make($providerClass));
        }
    }

    protected function registerAuditSkipRoutes(): void
    {
        if (! config()->has('audit.skip_routes')) {
            return;
        }

        $skipRoutes = config('afterburner-meetings.audit.skip_routes', []);

        config([
            'audit.skip_routes' => array_values(array_unique(array_merge(
                config('audit.skip_routes', []),
                $skipRoutes
            ))),
        ]);
    }

    protected function registerNavigation(): void
    {
        if (! class_exists(Navigation::class)) {
            return;
        }

        if (! config('afterburner-meetings.calendar.enabled', true)) {
            Navigation::register([
                'label' => 'Events',
                'route' => 'teams.meetings.index',
                'route_params' => fn () => $this->currentTeamRouteParams(),
                'icon' => 'user-group',
                'order' => 20,
                'permission' => fn ($user) => $this->canViewMeetings($user),
                'active' => fn () => request()->routeIs('teams.meetings.*'),
            ]);

            return;
        }

        Navigation::register([
            'label' => 'Events',
            'icon' => 'user-group',
            'order' => 20,
            'permission' => fn ($user) => $this->canViewMeetings($user),
            'active' => fn () => request()->routeIs('teams.meetings.*'),
            'children' => [
                [
                    'label' => 'Meetings',
                    'route' => 'teams.meetings.index',
                    'route_params' => fn () => $this->currentTeamRouteParams(),
                    'active' => fn () => request()->routeIs(
                        'teams.meetings.index',
                        'teams.meetings.show',
                        'teams.meetings.in-progress',
                        'teams.meetings.completed',
                        'teams.meetings.create',
                        'teams.meetings.edit'
                    ),
                ],
                [
                    'label' => 'Calendar',
                    'route' => 'teams.meetings.calendar',
                    'route_params' => fn () => $this->currentTeamRouteParams(),
                    'active' => fn () => request()->routeIs('teams.meetings.calendar'),
                ],
            ],
        ]);
    }

    /**
     * @return array<string, int>
     */
    protected function currentTeamRouteParams(): array
    {
        $user = auth()->user();
        if (! $user || ! $user->currentTeam) {
            return [];
        }

        return ['team' => $user->currentTeam->id];
    }

    protected function canViewMeetings(mixed $user): bool
    {
        if (! $user || ! $user->currentTeam) {
            return false;
        }

        return $user->can('viewAny', Meeting::class);
    }

    protected function registerPlaybook(): void
    {
        if (! class_exists(Playbook::class)) {
            return;
        }

        Playbook::register([
            'key' => 'meetings',
            'label' => 'Meetings',
            'order' => 20,
            'path' => __DIR__.'/../../playbook',
            'enabled' => fn () => config('afterburner-meetings.enabled', true),
            'permission' => fn ($user) => $user?->can('viewAny', Meeting::class) ?? false,
        ]);
    }

    protected function registerVotingEventListeners(): void
    {
        if (! class_exists(BallotPublished::class) || ! VotingIntegration::isEnabled()) {
            return;
        }

        Event::listen(BallotPublished::class, SyncMeetingBallotContext::class);
        Event::listen(BallotClosed::class, SyncMeetingBallotContext::class);
    }

    protected function registerActionItemEventListeners(): void
    {
        Event::listen(
            MeetingActionItemAssigned::class,
            NotifyMeetingActionItemAssignee::class
        );
    }

    protected function registerPackageSeeder(): void
    {
        if (class_exists(PackageSeederRegistry::class)) {
            PackageSeederRegistry::register(MeetingsPermissionsSeeder::class);
        }
    }

    protected function registerDocumentGuards(): void
    {
        if (! class_exists(Folder::class) || ! class_exists(Document::class)) {
            return;
        }

        if ($this->app->bound('afterburner-meetings.document-guards-registered')) {
            return;
        }

        $this->app->instance('afterburner-meetings.document-guards-registered', true);

        Gate::before(function ($user, $ability, $arguments) {
            if (! in_array($ability, ['delete', 'update'], true)) {
                return null;
            }

            $subject = $arguments[0] ?? null;

            if ($subject instanceof Folder) {
                if (MeetingsDocumentFolder::isProtected($subject)) {
                    return false;
                }

                return null;
            }

            if (! $subject instanceof Document) {
                return null;
            }

            if (MeetingCompiledDocumentGuard::isManaged($subject)) {
                return false;
            }

            return null;
        });

        Folder::updating(function (Folder $folder) {
            if (MeetingsDocumentFolder::isProtected($folder)
                || MeetingsDocumentFolder::wasProtected($folder)) {
                throw new \RuntimeException('This folder is managed by Meetings and cannot be renamed or moved.');
            }
        });

        Folder::deleting(function (Folder $folder) {
            if (MeetingsDocumentFolder::isProtected($folder)) {
                throw new \RuntimeException('This folder is managed by Meetings and cannot be deleted.');
            }
        });
    }
}
