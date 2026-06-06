<?php

namespace Afterburner\Meetings\Support;

use Afterburner\Documents\Models\Document;
use App\Models\Team;
use App\Models\User;
use App\Support\TeamPermissionGate;

class MeetingsDocumentLinkUi
{
    public const MIN_SEARCH_LENGTH = 2;

    public static function enabled(): bool
    {
        if (! config('afterburner-meetings.enabled', true)) {
            return false;
        }

        return DocumentsIntegration::isEnabled();
    }

    public static function canShowLinkAction(User $user, Team $team, Document $document): bool
    {
        if (! static::enabled()) {
            return false;
        }

        if ($document->team_id !== $team->id) {
            return false;
        }

        if ($document->upload_status !== 'completed') {
            return false;
        }

        return TeamPermissionGate::allows($user, $team->id, 'manage_meetings');
    }

    public static function searchIsActive(string $search): bool
    {
        return mb_strlen(trim($search)) >= static::MIN_SEARCH_LENGTH;
    }
}
