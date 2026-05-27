<?php

namespace Afterburner\Meetings\Livewire\Meetings;

use Afterburner\Documents\Models\Document;
use Afterburner\Meetings\Actions\AttachDocumentToMeeting;
use Afterburner\Meetings\Actions\DetachDocumentFromMeeting;
use Afterburner\Meetings\Models\Meeting;
use Afterburner\Meetings\Support\DocumentsIntegration;
use App\Models\Team;
use App\Traits\InteractsWithBanner;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class MeetingDocuments extends Component
{
    use InteractsWithBanner;

    public int $teamId;

    public int $meetingId;

    public bool $showAttachModal = false;

    public bool $showPreviewModal = false;

    public ?int $previewDocumentId = null;

    public string $documentSearch = '';

    public function mount(int $teamId, int $meetingId): void
    {
        abort_unless(DocumentsIntegration::isEnabled(), 404);

        $meeting = $this->meeting();

        if ($meeting->team_id !== $teamId) {
            abort(404);
        }

        abort_unless(Auth::user()->can('view', $meeting), 403);

        $this->teamId = $teamId;
        $this->meetingId = $meetingId;
    }

    public function openAttachModal(): void
    {
        abort_unless($this->canManageDocuments(), 403);

        $this->documentSearch = '';
        $this->showAttachModal = true;
    }

    public function closeAttachModal(): void
    {
        $this->showAttachModal = false;
        $this->documentSearch = '';
    }

    public function attachDocument(int $documentId): void
    {
        abort_unless($this->canManageDocuments(), 403);

        $meeting = $this->meeting();
        $document = Document::query()
            ->where('team_id', $this->teamId)
            ->findOrFail($documentId);

        try {
            app(AttachDocumentToMeeting::class)->execute($meeting, $document, Auth::user());
            $this->banner(__('Document attached to meeting.'));
            $this->closeAttachModal();
        } catch (\Throwable $exception) {
            $this->dangerBanner($exception->getMessage());
        }
    }

    public function detachDocument(int $documentId): void
    {
        abort_unless($this->canManageDocuments(), 403);

        $meeting = $this->meeting();
        $document = Document::query()
            ->where('team_id', $this->teamId)
            ->findOrFail($documentId);

        try {
            app(DetachDocumentFromMeeting::class)->execute($meeting, $document, Auth::user());
            $this->banner(__('Document removed from meeting.'));

            if ($this->previewDocumentId === $documentId) {
                $this->closePreview();
            }
        } catch (\Throwable $exception) {
            $this->dangerBanner($exception->getMessage());
        }
    }

    public function openPreview(int $documentId): void
    {
        $document = $this->linkedDocument($documentId);

        abort_unless($document->isPreviewableInBrowser(), 404);
        abort_unless(Auth::user()->can('view', $document), 403);

        $this->previewDocumentId = $documentId;
        $this->showPreviewModal = true;
    }

    public function closePreview(): void
    {
        $this->showPreviewModal = false;
        $this->previewDocumentId = null;
    }

    protected function linkedDocument(int $documentId): Document
    {
        return $this->meeting()
            ->linkedDocuments()
            ->where('documents.id', $documentId)
            ->firstOrFail();
    }

    protected function meeting(): Meeting
    {
        return Meeting::query()
            ->where('team_id', $this->teamId)
            ->findOrFail($this->meetingId);
    }

    protected function canManageDocuments(): bool
    {
        return Auth::user()->can('attachDocuments', $this->meeting());
    }

    public function render()
    {
        $meeting = $this->meeting()->load(['linkedDocuments.uploader']);
        $team = Team::query()->findOrFail($this->teamId);

        $linkedDocumentIds = $meeting->linkedDocuments->pluck('id');

        $availableDocuments = Document::query()
            ->forTeam($this->teamId)
            ->where('upload_status', 'completed')
            ->when($linkedDocumentIds->isNotEmpty(), fn ($query) => $query->whereNotIn('id', $linkedDocumentIds))
            ->when(filled($this->documentSearch), function ($query) {
                $term = '%'.$this->documentSearch.'%';
                $query->where(function ($inner) use ($term) {
                    $inner->where('name', 'like', $term)
                        ->orWhere('filename', 'like', $term);
                });
            })
            ->orderBy('name')
            ->limit(25)
            ->get();

        $previewDocument = null;
        $previewUrl = null;

        if ($this->showPreviewModal && $this->previewDocumentId) {
            $previewDocument = $meeting->linkedDocuments->firstWhere('id', $this->previewDocumentId);
            if ($previewDocument) {
                $previewUrl = route('teams.documents.download', [
                    'team' => $team,
                    'document' => $previewDocument,
                ]);
            }
        }

        return view('afterburner-meetings::meetings.livewire.meeting-documents', [
            'team' => $team,
            'meeting' => $meeting,
            'linkedDocuments' => $meeting->linkedDocuments,
            'availableDocuments' => $availableDocuments,
            'canManageDocuments' => $this->canManageDocuments(),
            'previewDocument' => $previewDocument,
            'previewUrl' => $previewUrl,
        ]);
    }
}
