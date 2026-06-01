<?php

namespace Afterburner\Meetings\Actions;

use Afterburner\Meetings\Exceptions\MeetingsException;
use Afterburner\Meetings\Models\MeetingAgendaItem;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class ReorderMeetingAgendaItem
{
    public function execute(MeetingAgendaItem $agendaItem, User $user, string $direction): MeetingAgendaItem
    {
        Gate::forUser($user)->authorize('update', $agendaItem);

        if ($agendaItem->team_id !== $user->currentTeam?->id) {
            throw new MeetingsException('You do not belong to this team.');
        }

        if (! in_array($direction, ['up', 'down'], true)) {
            throw new MeetingsException('Invalid reorder direction.');
        }

        $query = MeetingAgendaItem::query()
            ->where('meeting_id', $agendaItem->meeting_id)
            ->orderBy('sort_order')
            ->orderBy('id');

        $items = $query->get();
        $index = $items->search(fn (MeetingAgendaItem $item) => $item->id === $agendaItem->id);

        if ($index === false) {
            throw new MeetingsException('Agenda item not found.');
        }

        $swapIndex = $direction === 'up' ? $index - 1 : $index + 1;

        if ($swapIndex < 0 || $swapIndex >= $items->count()) {
            return $agendaItem;
        }

        $current = $items[$index];
        $neighbor = $items[$swapIndex];
        $currentOrder = $current->sort_order;
        $neighborOrder = $neighbor->sort_order;

        $current->update(['sort_order' => $neighborOrder]);
        $neighbor->update(['sort_order' => $currentOrder]);

        return $agendaItem->fresh(['reference']);
    }

    public function moveToPosition(MeetingAgendaItem $agendaItem, User $user, int $position): void
    {
        Gate::forUser($user)->authorize('update', $agendaItem);

        if ($agendaItem->team_id !== $user->currentTeam?->id) {
            throw new MeetingsException('You do not belong to this team.');
        }

        $items = MeetingAgendaItem::query()
            ->where('meeting_id', $agendaItem->meeting_id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $currentIndex = $items->search(fn (MeetingAgendaItem $item) => $item->id === $agendaItem->id);

        if ($currentIndex === false) {
            throw new MeetingsException('Agenda item not found.');
        }

        $position = max(0, min($position, $items->count() - 1));

        if ($currentIndex === $position) {
            return;
        }

        $reordered = $items->values();
        $moved = $reordered->splice($currentIndex, 1)->first();
        $reordered->splice($position, 0, [$moved]);

        foreach ($reordered->values() as $order => $item) {
            $nextSortOrder = $order + 1;

            if ($item->sort_order !== $nextSortOrder) {
                $item->update(['sort_order' => $nextSortOrder]);
            }
        }
    }
}
