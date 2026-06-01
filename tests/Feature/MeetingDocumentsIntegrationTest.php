<?php

namespace Afterburner\Meetings\Tests\Feature;

use Afterburner\Meetings\Actions\CreateMeeting;
use Afterburner\Meetings\Actions\UpdateMeeting;
use Afterburner\Meetings\Enums\MeetingStatus;
use Afterburner\Meetings\Enums\MeetingType;
use Afterburner\Meetings\Livewire\Meetings\Show;
use Afterburner\Meetings\Models\Meeting;
use Afterburner\Meetings\Support\DocumentsIntegration;
use Afterburner\Meetings\Support\MeetingPackageDataBuilder;
use Afterburner\Meetings\Tests\TestCase;
use Illuminate\Support\Facades\Auth;

class MeetingDocumentsIntegrationTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            \Livewire\LivewireServiceProvider::class,
            \Afterburner\Meetings\Providers\MeetingsServiceProvider::class,
        ];
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../migrations');
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
    }

    public function test_meeting_show_renders_when_documents_are_not_available(): void
    {
        [$manager, $team] = $this->createTeamWithUser(['manage_meetings']);
        Auth::login($manager);

        $meeting = $this->scheduledMeeting($manager, $team);

        $this->assertFalse(DocumentsIntegration::isAvailable());

        $component = new Show;
        $component->teamId = $team->id;
        $component->meetingId = $meeting->id;

        $view = $component->render();
        $data = $view->getData();

        $this->assertFalse($data['documentsEnabled']);
        $this->assertFalse($data['hasLinkedDocuments']);
        $this->assertTrue($data['linkedDocuments']->isEmpty());
    }

    public function test_meeting_linked_documents_relation_is_empty_when_documents_are_not_available(): void
    {
        [$manager, $team] = $this->createTeamWithUser(['manage_meetings']);

        $meeting = $this->scheduledMeeting($manager, $team);

        $this->assertFalse(DocumentsIntegration::isAvailable());
        $this->assertTrue($meeting->linkedDocuments()->get()->isEmpty());
        $this->assertTrue(DocumentsIntegration::linkedDocumentsFor($meeting)->isEmpty());
    }

    public function test_meeting_package_data_builder_omits_linked_documents_when_documents_are_not_available(): void
    {
        [$manager, $team] = $this->createTeamWithUser(['manage_meetings']);

        $meeting = app(UpdateMeeting::class)->execute(
            $this->scheduledMeeting($manager, $team),
            $manager,
            'Completed council meeting',
            MeetingType::Council,
            MeetingStatus::Completed,
            null,
            null,
            null,
            now(),
            ['manager'],
        );

        $data = app(MeetingPackageDataBuilder::class)->build($meeting);

        $this->assertSame([], $data['linkedDocuments']);
    }

    protected function scheduledMeeting($manager, $team): Meeting
    {
        $meeting = app(CreateMeeting::class)->execute(
            $team,
            $manager,
            'Council meeting',
            MeetingType::Council,
            targetRoleSlugs: ['manager'],
        );

        return app(UpdateMeeting::class)->execute(
            $meeting,
            $manager,
            $meeting->title,
            $meeting->type,
            MeetingStatus::Scheduled,
            $meeting->location,
            $meeting->virtual_link,
            $meeting->agenda_notes,
            $meeting->scheduled_at,
            $meeting->target_role_slugs,
        );
    }
}
