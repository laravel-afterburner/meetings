<?php

namespace Afterburner\Meetings\Support;

use Afterburner\Documents\Models\Document;
use Afterburner\Meetings\Models\Meeting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MeetingCompiledDocumentGuard
{
    public static function isManaged(Document $document): bool
    {
        if (! DocumentsIntegration::isAvailable()) {
            return false;
        }

        $document->loadMissing('folder');

        if ($document->folder === null) {
            return false;
        }

        if (! MeetingsDocumentFolder::isProtected($document->folder)) {
            return false;
        }

        if (! Schema::hasTable('document_links')) {
            return false;
        }

        return DB::table('document_links')
            ->where('document_id', $document->id)
            ->where('linkable_type', (new Meeting)->getMorphClass())
            ->exists();
    }

    public static function lockTooltip(): string
    {
        return 'This meeting package was saved from a completed meeting and cannot be edited, moved, or deleted here. Compile a new package from the meeting page if you need an updated copy.';
    }
}
