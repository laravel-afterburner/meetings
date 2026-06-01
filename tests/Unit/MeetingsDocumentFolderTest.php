<?php

namespace Afterburner\Meetings\Tests\Unit;

use Afterburner\Documents\Models\Folder;
use Afterburner\Meetings\Support\MeetingsDocumentFolder;
use Afterburner\Meetings\Tests\TestCase;

class MeetingsDocumentFolderTest extends TestCase
{
    public function test_protected_meetings_root_folder_cannot_be_renamed(): void
    {
        if (! class_exists(Folder::class)) {
            $this->markTestSkipped('Documents package is not available.');
        }

        [$user, $team] = $this->createTeamWithUser(['manage_meetings', 'view_documents']);

        $folder = MeetingsDocumentFolder::resolve($team, $user);

        $this->assertTrue(MeetingsDocumentFolder::isProtected($folder));

        $this->expectException(\RuntimeException::class);

        $folder->update(['name' => 'Renamed folder']);
    }
}
