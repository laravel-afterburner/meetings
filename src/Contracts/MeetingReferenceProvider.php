<?php

namespace Afterburner\Meetings\Contracts;

use Afterburner\Meetings\Enums\MeetingType;
use Afterburner\Meetings\Models\Meeting;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

interface MeetingReferenceProvider
{
    public function key(): string;

    public function label(): string;

    public function modelClass(): string;

    public function isAvailable(): bool;

    public function canLink(User $user, Team $team, Model $reference): bool;

    /**
     * @return Collection<int, Model>
     */
    public function search(Team $team, User $user, ?string $query = null, int $limit = 20): Collection;

    /**
     * @return Collection<int, Model>
     */
    public function suggestions(Team $team, User $user, MeetingType $meetingType, ?Meeting $meeting = null): Collection;

    public function agendaTitle(Model $reference): string;

    public function agendaSummary(Model $reference): ?string;

    public function viewUrl(Team $team, Model $reference): ?string;
}
