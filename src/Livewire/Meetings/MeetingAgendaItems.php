<?php

namespace Afterburner\Meetings\Livewire\Meetings;

use Afterburner\Meetings\Actions\AddSuggestedMeetingAgendaItems;
use Afterburner\Meetings\Actions\CreateMeetingAgendaItem;
use Afterburner\Meetings\Actions\DeleteMeetingAgendaItem;
use Afterburner\Meetings\Actions\LinkMeetingAgendaReference;
use Afterburner\Meetings\Actions\ReorderMeetingAgendaItem;
use Afterburner\Meetings\Actions\UpdateMeetingAgendaItem;
use Afterburner\Meetings\Enums\AgendaSection;
use Afterburner\Meetings\Models\Meeting;
use Afterburner\Meetings\Models\MeetingAgendaItem;
use Afterburner\Meetings\Support\MeetingReferenceRegistry;
use App\Models\Team;
use App\Traits\InteractsWithBanner;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class MeetingAgendaItems extends Component
{
    use InteractsWithBanner;

    public int $teamId;

    public int $meetingId;

    public bool $readOnly = false;

    public bool $embedded = false;

    public bool $showFormModal = false;

    public bool $showLinkModal = false;

    public ?int $editingAgendaItemId = null;

    public string $title = '';

    public string $notes = '';

    public string $section = '';

    public string $linkProviderKey = '';

    public string $referenceSearch = '';

    public ?int $selectedReferenceId = null;

    public function mount(int $teamId, int $meetingId): void
    {
        $meeting = $this->meeting();

        if ($meeting->team_id !== $teamId) {
            abort(404);
        }

        abort_unless(Auth::user()->can('viewAny', [MeetingAgendaItem::class, $meeting]), 403);

        $this->teamId = $teamId;
        $this->meetingId = $meetingId;
    }

    public function openCreateModal(): void
    {
        abort_unless($this->canManageAgenda(), 403);

        $this->resetForm();
        $this->showFormModal = true;
    }

    public function openEditModal(int $agendaItemId): void
    {
        abort_unless($this->canManageAgenda(), 403);

        $item = $this->findAgendaItem($agendaItemId);

        $this->editingAgendaItemId = $item->id;
        $this->title = $item->title;
        $this->notes = $item->notes ?? '';
        $this->section = $item->section?->value ?? '';
        $this->showFormModal = true;
    }

    public function openLinkModal(): void
    {
        abort_unless($this->canManageAgenda(), 403);

        $this->resetLinkForm();
        $this->showLinkModal = true;
    }

    public function closeFormModal(): void
    {
        $this->showFormModal = false;
        $this->resetForm();
    }

    public function closeLinkModal(): void
    {
        $this->showLinkModal = false;
        $this->resetLinkForm();
    }

    public function updatedLinkProviderKey(): void
    {
        $this->selectedReferenceId = null;
        $this->referenceSearch = '';
    }

    public function saveAgendaItem(): void
    {
        if ($this->editingAgendaItemId) {
            $this->updateAgendaItem();

            return;
        }

        $this->createAgendaItem();
    }

    public function createAgendaItem(): void
    {
        abort_unless($this->canManageAgenda(), 403);

        $this->validate([
            'title' => 'required|string|max:255',
            'notes' => 'nullable|string|max:5000',
            'section' => 'nullable|in:'.implode(',', array_column(AgendaSection::cases(), 'value')),
        ]);

        try {
            app(CreateMeetingAgendaItem::class)->execute(
                $this->meeting(),
                Auth::user(),
                $this->title,
                filled($this->notes) ? $this->notes : null,
                filled($this->section) ? AgendaSection::from($this->section) : null,
            );

            $this->banner(__('Agenda item added.'));
            $this->closeFormModal();
        } catch (\Throwable $exception) {
            $this->dangerBanner($exception->getMessage());
        }
    }

    public function updateAgendaItem(): void
    {
        abort_unless($this->canManageAgenda(), 403);

        $this->validate([
            'title' => 'required|string|max:255',
            'notes' => 'nullable|string|max:5000',
            'section' => 'nullable|in:'.implode(',', array_column(AgendaSection::cases(), 'value')),
        ]);

        try {
            app(UpdateMeetingAgendaItem::class)->execute(
                $this->findAgendaItem($this->editingAgendaItemId),
                Auth::user(),
                $this->title,
                filled($this->notes) ? $this->notes : null,
                filled($this->section) ? AgendaSection::from($this->section) : null,
            );

            $this->banner(__('Agenda item updated.'));
            $this->closeFormModal();
        } catch (\Throwable $exception) {
            $this->dangerBanner($exception->getMessage());
        }
    }

    public function linkReference(): void
    {
        abort_unless($this->canManageAgenda(), 403);

        $this->validate([
            'linkProviderKey' => 'required|string',
            'selectedReferenceId' => 'required|integer',
            'section' => 'nullable|in:'.implode(',', array_column(AgendaSection::cases(), 'value')),
        ]);

        try {
            app(LinkMeetingAgendaReference::class)->execute(
                $this->meeting(),
                Auth::user(),
                $this->linkProviderKey,
                $this->selectedReferenceId,
                filled($this->section) ? AgendaSection::from($this->section) : null,
            );

            $this->banner(__('Linked record added to the agenda.'));
            $this->closeLinkModal();
        } catch (\Throwable $exception) {
            $this->addError('selectedReferenceId', $exception->getMessage());
        }
    }

    public function addSuggestedItems(): void
    {
        abort_unless($this->canManageAgenda(), 403);

        try {
            $created = app(AddSuggestedMeetingAgendaItems::class)->execute(
                $this->meeting(),
                Auth::user(),
            );

            if ($created->isEmpty()) {
                $this->banner(__('No suggested agenda items are available right now.'));

                return;
            }

            $this->banner(__(':count suggested agenda item(s) added.', ['count' => $created->count()]));
        } catch (\Throwable $exception) {
            $this->dangerBanner($exception->getMessage());
        }
    }

    public function deleteAgendaItem(int $agendaItemId): void
    {
        abort_unless($this->canManageAgenda(), 403);

        try {
            app(DeleteMeetingAgendaItem::class)->execute($this->findAgendaItem($agendaItemId), Auth::user());
            $this->banner(__('Agenda item removed.'));
        } catch (\Throwable $exception) {
            $this->dangerBanner($exception->getMessage());
        }
    }

    public function sortAgendaItem(int $agendaItemId, int $position): void
    {
        abort_unless($this->canManageAgenda(), 403);

        try {
            app(ReorderMeetingAgendaItem::class)->moveToPosition(
                $this->findAgendaItem($agendaItemId),
                Auth::user(),
                $position,
            );
        } catch (\Throwable $exception) {
            $this->dangerBanner($exception->getMessage());
        }
    }

    protected function meeting(): Meeting
    {
        return Meeting::query()
            ->where('team_id', $this->teamId)
            ->findOrFail($this->meetingId);
    }

    protected function team(): Team
    {
        return Team::query()->findOrFail($this->teamId);
    }

    protected function findAgendaItem(int $agendaItemId): MeetingAgendaItem
    {
        return MeetingAgendaItem::query()
            ->where('meeting_id', $this->meetingId)
            ->where('team_id', $this->teamId)
            ->findOrFail($agendaItemId);
    }

    protected function canManageAgenda(): bool
    {
        if ($this->readOnly) {
            return false;
        }

        return Auth::user()->can('create', [MeetingAgendaItem::class, $this->meeting()]);
    }

    protected function resetForm(): void
    {
        $this->editingAgendaItemId = null;
        $this->title = '';
        $this->notes = '';
        $this->section = '';
        $this->resetValidation();
    }

    protected function resetLinkForm(): void
    {
        $this->linkProviderKey = '';
        $this->referenceSearch = '';
        $this->selectedReferenceId = null;
        $this->section = '';
        $this->resetValidation();
    }

    public function render()
    {
        $meeting = $this->meeting();
        $registry = app(MeetingReferenceRegistry::class);
        $availableProviders = $registry->available();
        $canManage = $this->canManageAgenda();

        $searchResults = collect();

        if ($this->showLinkModal && filled($this->linkProviderKey)) {
            $provider = $registry->get($this->linkProviderKey);

            if ($provider !== null) {
                $linkedReferenceKeys = MeetingAgendaItem::query()
                    ->where('meeting_id', $this->meetingId)
                    ->whereNotNull('reference_type')
                    ->get(['reference_type', 'reference_id'])
                    ->map(fn (MeetingAgendaItem $item) => $item->reference_type.':'.$item->reference_id)
                    ->all();

                $searchResults = $provider->search(
                    $this->team(),
                    Auth::user(),
                    filled($this->referenceSearch) ? $this->referenceSearch : null,
                )->reject(function ($reference) use ($linkedReferenceKeys) {
                    $key = $reference->getMorphClass().':'.$reference->getKey();

                    return in_array($key, $linkedReferenceKeys, true);
                })->values();
            }
        }

        $agendaItems = MeetingAgendaItem::query()
            ->with(['reference', 'creator'])
            ->where('meeting_id', $this->meetingId)
            ->where('team_id', $this->teamId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return view('afterburner-meetings::meetings.livewire.meeting-agenda-items', [
            'team' => $this->team(),
            'meeting' => $meeting,
            'agendaItems' => $agendaItems,
            'canManageAgenda' => $canManage,
            'availableProviders' => $availableProviders,
            'searchResults' => $searchResults,
            'sectionOptions' => AgendaSection::cases(),
            'hasSuggestions' => $canManage && collect($availableProviders)->contains(
                fn ($provider) => $provider->suggestions($this->team(), Auth::user(), $meeting->type, $meeting)->isNotEmpty()
            ),
            'embedded' => $this->embedded,
        ]);
    }
}
