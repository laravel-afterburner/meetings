<?php

namespace Afterburner\Meetings\Support;

use Afterburner\Documents\Actions\CreateFolder;
use Afterburner\Documents\Models\Folder;
use App\Models\Team;
use App\Models\User;

class MeetingsDocumentFolder
{
    public static function folderName(): string
    {
        return (string) config('afterburner-meetings.documents_package.folder_name', 'Meetings');
    }

    public static function isProtected(Folder $folder): bool
    {
        return self::matchesProtectedRootFolder($folder->parent_id, $folder->name);
    }

    public static function wasProtected(Folder $folder): bool
    {
        $parentId = $folder->getOriginal('parent_id');
        $name = $folder->getOriginal('name');

        if ($parentId === null && $name === null) {
            return self::isProtected($folder);
        }

        return self::matchesProtectedRootFolder($parentId, $name);
    }

    public static function matchesProtectedRootFolder(?int $parentId, string $name): bool
    {
        if ($parentId !== null) {
            return false;
        }

        return $name === self::folderName();
    }

    public static function lockTooltip(): string
    {
        return 'This folder was created automatically for meeting records and cannot be renamed, moved, or deleted. Files are managed from the completed meeting page.';
    }

    public static function resolve(Team|int $team, User $user): Folder
    {
        $teamId = $team instanceof Team ? $team->id : $team;
        $name = self::folderName();

        $existing = Folder::query()
            ->where('team_id', $teamId)
            ->whereNull('parent_id')
            ->where('name', $name)
            ->first();

        if ($existing) {
            return $existing;
        }

        return app(CreateFolder::class)->execute(
            $teamId,
            null,
            $name,
            $user,
        );
    }
}
