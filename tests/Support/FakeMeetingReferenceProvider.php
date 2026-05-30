<?php

namespace Afterburner\Meetings\Tests\Support;

use Afterburner\Meetings\Contracts\MeetingReferenceProvider;
use Afterburner\Meetings\Enums\MeetingType;
use Afterburner\Meetings\Models\Meeting;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class FakeMeetingReferenceProvider implements MeetingReferenceProvider
{
    public function key(): string
    {
        return 'fake_record';
    }

    public function label(): string
    {
        return 'Fake record';
    }

    public function modelClass(): string
    {
        return FakeAgendaReference::class;
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function canLink(User $user, Team $team, Model $reference): bool
    {
        return true;
    }

    public function search(Team $team, User $user, ?string $query = null, int $limit = 20): Collection
    {
        return FakeAgendaReference::query()
            ->where('team_id', $team->id)
            ->when(filled($query), fn ($builder) => $builder->where('title', 'like', '%'.$query.'%'))
            ->limit($limit)
            ->get();
    }

    public function suggestions(Team $team, User $user, MeetingType $meetingType, ?Meeting $meeting = null): Collection
    {
        return FakeAgendaReference::query()
            ->where('team_id', $team->id)
            ->limit(2)
            ->get();
    }

    public function agendaTitle(Model $reference): string
    {
        /** @var FakeAgendaReference $reference */
        return 'Fake: '.$reference->title;
    }

    public function agendaSummary(Model $reference): ?string
    {
        /** @var FakeAgendaReference $reference */
        return $reference->summary;
    }

    public function viewUrl(Team $team, Model $reference): ?string
    {
        return null;
    }
}
