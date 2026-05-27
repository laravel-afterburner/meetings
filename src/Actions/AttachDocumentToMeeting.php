<?php

namespace Afterburner\Meetings\Actions;

use Afterburner\Documents\Actions\LinkDocument;
use Afterburner\Documents\Models\Document;
use Afterburner\Meetings\Exceptions\MeetingsException;
use Afterburner\Meetings\Models\Meeting;
use Afterburner\Meetings\Support\DocumentsIntegration;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class AttachDocumentToMeeting
{
    public function execute(Meeting $meeting, Document $document, User $user): void
    {
        if (! DocumentsIntegration::isEnabled()) {
            throw new MeetingsException('Document attachments are not available.');
        }

        Gate::forUser($user)->authorize('attachDocuments', $meeting);

        if ($document->team_id !== $meeting->team_id) {
            throw new MeetingsException('The document must belong to the same team as this meeting.');
        }

        app(LinkDocument::class)->execute($document, $meeting, $user);
    }
}
