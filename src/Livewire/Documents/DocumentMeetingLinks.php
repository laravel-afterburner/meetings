<?php

namespace Afterburner\Meetings\Livewire\Documents;

use Afterburner\Documents\Models\Document;
use Afterburner\Meetings\Actions\AttachDocumentToMeeting;
use Afterburner\Meetings\Actions\DetachDocumentFromMeeting;
use Afterburner\Meetings\Models\Meeting;
use Afterburner\Meetings\Support\MeetingsDocumentLinkUi;
use App\Models\Team;
use App\Traits\InteractsWithBanner;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

class DocumentMeetingLinks extends Component
{
    use InteractsWithBanner;

    public int $teamId;

    public bool $showModal = false;

    public ?int $documentId = null;

    public string $meetingSearch = '';

    public function mount(int $teamId): void
    {
        abort_unless(MeetingsDocumentLinkUi::enabled(), 404);

        $this->teamId = $teamId;
    }

    #[On('open-document-meeting-link-modal')]
    public function openModal(int $documentId, int $teamId): void
    {
        abort_unless($teamId === $this->teamId, 404);

        $team = Team::query()->findOrFail($teamId);
        $document = Document::query()
            ->where('team_id', $teamId)
            ->findOrFail($documentId);

        abort_unless(Auth::user()->can('view', $document), 403);
        abort_unless(MeetingsDocumentLinkUi::canShowLinkAction(Auth::user(), $team, $document), 403);

        $this->documentId = $document->id;
        $this->meetingSearch = '';
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->documentId = null;
        $this->meetingSearch = '';
    }

    public function attachToMeeting(int $meetingId): void
    {
        $document = $this->document();
        $meeting = $this->findMeeting($meetingId);

        try {
            app(AttachDocumentToMeeting::class)->execute($meeting, $document, Auth::user());
            $this->banner(__('Document linked to meeting.'));
        } catch (\Throwable $exception) {
            $this->dangerBanner($exception->getMessage());
        }
    }

    public function detachFromMeeting(int $meetingId): void
    {
        $document = $this->document();
        $meeting = $this->findMeeting($meetingId);

        try {
            app(DetachDocumentFromMeeting::class)->execute($meeting, $document, Auth::user());
            $this->banner(__('Document removed from meeting.'));
        } catch (\Throwable $exception) {
            $this->dangerBanner($exception->getMessage());
        }
    }

    protected function document(): Document
    {
        abort_unless($this->documentId, 404);

        return Document::query()
            ->where('team_id', $this->teamId)
            ->findOrFail($this->documentId);
    }

    protected function findMeeting(int $meetingId): Meeting
    {
        return Meeting::query()
            ->where('team_id', $this->teamId)
            ->findOrFail($meetingId);
    }

    protected function linkedMeetings(): Collection
    {
        if (! $this->documentId) {
            return collect();
        }

        return Meeting::query()
            ->where('team_id', $this->teamId)
            ->whereHas('linkedDocuments', fn ($query) => $query->where('documents.id', $this->documentId))
            ->orderBy('title')
            ->get();
    }

    protected function searchMeetings(): Collection
    {
        if (! $this->showModal || ! $this->documentId || ! MeetingsDocumentLinkUi::searchIsActive($this->meetingSearch)) {
            return collect();
        }

        $term = '%'.trim($this->meetingSearch).'%';

        return Meeting::query()
            ->where('team_id', $this->teamId)
            ->where('title', 'like', $term)
            ->whereDoesntHave('linkedDocuments', fn ($query) => $query->where('documents.id', $this->documentId))
            ->orderBy('title')
            ->limit(25)
            ->get()
            ->filter(fn (Meeting $meeting) => Auth::user()->can('attachDocuments', $meeting))
            ->values();
    }

    public function render()
    {
        $document = $this->documentId
            ? Document::query()->where('team_id', $this->teamId)->find($this->documentId)
            : null;

        return view('afterburner-meetings::documents.livewire.document-meeting-links', [
            'document' => $document,
            'linkedMeetings' => $this->linkedMeetings(),
            'searchMeetings' => $this->searchMeetings(),
            'searchIsActive' => MeetingsDocumentLinkUi::searchIsActive($this->meetingSearch),
        ]);
    }
}
