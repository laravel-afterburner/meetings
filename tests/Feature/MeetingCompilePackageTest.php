<?php

namespace Afterburner\Meetings\Tests\Feature;

use Afterburner\Documents\Models\Folder;
use Afterburner\Meetings\Actions\CompileMeetingPackage;
use Afterburner\Meetings\Actions\CompleteMeeting;
use Afterburner\Meetings\Actions\CreateMeeting;
use Afterburner\Meetings\Actions\CreateMeetingActionItem;
use Afterburner\Meetings\Actions\StartMeeting;
use Afterburner\Meetings\Actions\UpdateMeeting;
use Afterburner\Meetings\Enums\MeetingStatus;
use Afterburner\Meetings\Enums\MeetingType;
use Afterburner\Meetings\Livewire\Meetings\Completed;
use Afterburner\Meetings\Support\MeetingsDocumentFolder;
use Afterburner\Meetings\Tests\TestCase;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class MeetingCompilePackageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! class_exists(Folder::class)) {
            return;
        }

        Storage::fake('r2');
        Storage::fake('documents-uploads');
    }

    public function test_compile_creates_pdf_in_meetings_folder(): void
    {
        if (! class_exists(Pdf::class)) {
            $this->markTestSkipped('barryvdh/laravel-dompdf is not installed.');
        }

        if (! class_exists(Folder::class)) {
            $this->markTestSkipped('Documents package is not available.');
        }

        [$manager, $team] = $this->createTeamWithUser(['manage_meetings', 'view_documents', 'create_documents']);

        $meeting = $this->completedMeeting($manager, $team);

        $document = app(CompileMeetingPackage::class)->execute($meeting, $manager);

        $folder = Folder::query()
            ->where('team_id', $team->id)
            ->where('name', MeetingsDocumentFolder::folderName())
            ->first();

        $this->assertNotNull($folder);
        $this->assertSame($folder->id, $document->folder_id);
        $this->assertSame('completed', $document->upload_status);
        $this->assertSame('application/pdf', $document->mime_type);

        $this->assertDatabaseHas('document_links', [
            'document_id' => $document->id,
            'linkable_type' => $meeting->getMorphClass(),
            'linkable_id' => $meeting->id,
        ]);
    }

    public function test_completed_page_shows_confirm_modal_when_open_action_items_exist(): void
    {
        if (! class_exists(Pdf::class)) {
            $this->markTestSkipped('barryvdh/laravel-dompdf is not installed.');
        }

        [$manager, $team] = $this->createTeamWithUser(['manage_meetings', 'view_documents', 'create_documents']);
        $assignee = $this->createAdditionalUser($team, ['manage_meetings'], 'assignee@example.com');
        Auth::login($manager);

        $meeting = $this->completedMeeting($manager, $team);

        app(CreateMeetingActionItem::class)->execute(
            $meeting,
            $manager,
            'Send recap email',
            $assignee->id,
        );

        $component = new Completed;
        $component->mount($team, $meeting);
        $component->requestCompilePackage();

        $this->assertTrue($component->showCompileConfirmModal);

        $component->cancelCompilePackage();

        $this->assertFalse($component->showCompileConfirmModal);
    }

    protected function completedMeeting($manager, $team)
    {
        $meeting = app(CreateMeeting::class)->execute(
            $team,
            $manager,
            'Annual general meeting',
            MeetingType::Agm,
            targetRoleSlugs: ['manager'],
        );

        $meeting = app(UpdateMeeting::class)->execute(
            $meeting,
            $manager,
            $meeting->title,
            $meeting->type,
            MeetingStatus::Scheduled,
            'Community hall',
            null,
            'Budget and insurance review.',
            $meeting->scheduled_at,
            $meeting->target_role_slugs,
        );

        $meeting = app(StartMeeting::class)->execute($meeting, $manager, []);

        return app(CompleteMeeting::class)->execute($meeting->fresh(), $manager);
    }
}
