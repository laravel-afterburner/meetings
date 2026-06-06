<?php

namespace Afterburner\Meetings\Livewire\Meetings;

use Afterburner\Meetings\Actions\CompleteMeetingActionItem;
use Afterburner\Meetings\Actions\CreateMeetingActionItem;
use Afterburner\Meetings\Actions\DeleteMeetingActionItem;
use Afterburner\Meetings\Actions\UpdateMeetingActionItem;
use Afterburner\Meetings\Enums\ActionItemStatus;
use Afterburner\Meetings\Models\Meeting;
use Afterburner\Meetings\Models\MeetingActionItem;
use Afterburner\Meetings\Support\MeetingActionItemAssigneeService;
use App\Support\TeamDateTime;
use App\Models\Team;
use App\Traits\InteractsWithBanner;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class MeetingActionItems extends Component
{
    use InteractsWithBanner;

    public int $teamId;

    public int $meetingId;

    public bool $readOnly = false;

    public bool $embedded = false;

    public string $assigneeScope = 'team';

    public bool $showFormModal = false;

    public ?int $editingActionItemId = null;

    public string $title = '';

    public string $description = '';

    public ?int $assignedToUserId = null;

    public ?string $dueAt = null;

    public string $status = 'open';

    public function mount(int $teamId, int $meetingId): void
    {
        $meeting = $this->meeting();

        if ($meeting->team_id !== $teamId) {
            abort(404);
        }

        abort_unless(Auth::user()->can('view', $meeting), 403);
        abort_unless($this->canViewActionItems(), 403);

        $this->teamId = $teamId;
        $this->meetingId = $meetingId;
    }

    public function openCreateModal(): void
    {
        abort_unless($this->canManageActionItems(), 403);

        $this->resetForm();
        $this->editingActionItemId = null;
        $this->showFormModal = true;
    }

    public function openEditModal(int $actionItemId): void
    {
        $actionItem = $this->findActionItem($actionItemId);
        abort_unless($this->canManageActionItems(), 403);

        $this->editingActionItemId = $actionItem->id;
        $this->title = $actionItem->title;
        $this->description = $actionItem->description ?? '';
        $this->assignedToUserId = $actionItem->assigned_to_user_id;
        $this->dueAt = $actionItem->due_at
            ? $actionItem->due_at->timezone(TeamDateTime::teamTimezone($this->team()))->format('Y-m-d')
            : null;
        $this->status = $actionItem->status->value;
        $this->showFormModal = true;
    }

    public function closeFormModal(): void
    {
        $this->showFormModal = false;
        $this->resetForm();
    }

    public function saveActionItem(): void
    {
        if ($this->editingActionItemId) {
            $this->updateActionItem();

            return;
        }

        $this->createActionItem();
    }

    public function createActionItem(): void
    {
        abort_unless($this->canManageActionItems(), 403);

        try {
            app(CreateMeetingActionItem::class)->execute(
                $this->meeting(),
                Auth::user(),
                $this->title,
                filled($this->description) ? $this->description : null,
                $this->assignedToUserId,
                $this->parsedDueAt(),
            );

            $this->banner(__('Action item created.'));
            $this->closeFormModal();
        } catch (\Throwable $exception) {
            $this->dangerBanner($exception->getMessage());
        }
    }

    public function updateActionItem(): void
    {
        $actionItem = $this->findActionItem($this->editingActionItemId);

        try {
            if ($this->canManageActionItems()) {
                app(UpdateMeetingActionItem::class)->execute(
                    $actionItem,
                    Auth::user(),
                    $this->title,
                    filled($this->description) ? $this->description : null,
                    $this->assignedToUserId,
                    $this->parsedDueAt(),
                    ActionItemStatus::from($this->status),
                    assigneeFieldsProvided: true,
                    dueAtProvided: true,
                    descriptionProvided: true,
                );
            } else {
                app(UpdateMeetingActionItem::class)->execute(
                    $actionItem,
                    Auth::user(),
                    status: ActionItemStatus::from($this->status),
                );
            }

            $this->banner(__('Action item updated.'));
            $this->closeFormModal();
        } catch (\Throwable $exception) {
            $this->dangerBanner($exception->getMessage());
        }
    }

    public function completeActionItem(int $actionItemId): void
    {
        abort_if($this->readOnly, 403);

        $actionItem = $this->findActionItem($actionItemId);

        try {
            app(CompleteMeetingActionItem::class)->execute($actionItem, Auth::user());
            $this->banner(__('Action item marked complete.'));
        } catch (\Throwable $exception) {
            $this->dangerBanner($exception->getMessage());
        }
    }

    public function updateStatus(int $actionItemId, string $status): void
    {
        abort_if($this->readOnly, 403);

        $actionItem = $this->findActionItem($actionItemId);

        try {
            app(UpdateMeetingActionItem::class)->execute(
                $actionItem,
                Auth::user(),
                status: ActionItemStatus::from($status),
            );

            $this->banner(__('Action item status updated.'));
        } catch (\Throwable $exception) {
            $this->dangerBanner($exception->getMessage());
        }
    }

    public function deleteActionItem(int $actionItemId): void
    {
        abort_unless($this->canManageActionItems(), 403);

        try {
            app(DeleteMeetingActionItem::class)->execute($this->findActionItem($actionItemId), Auth::user());
            $this->banner(__('Action item deleted.'));
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

    protected function findActionItem(int $actionItemId): MeetingActionItem
    {
        return MeetingActionItem::query()
            ->where('meeting_id', $this->meetingId)
            ->where('team_id', $this->teamId)
            ->findOrFail($actionItemId);
    }

    protected function canManageActionItems(): bool
    {
        if ($this->readOnly) {
            return false;
        }

        return Auth::user()->can('create', [MeetingActionItem::class, $this->meeting()]);
    }

    protected function canViewActionItems(): bool
    {
        if ($this->canManageActionItems()) {
            return true;
        }

        return MeetingActionItem::query()
            ->where('meeting_id', $this->meetingId)
            ->where('team_id', $this->teamId)
            ->assignedTo(Auth::id())
            ->exists();
    }

    protected function resetForm(): void
    {
        $this->editingActionItemId = null;
        $this->title = '';
        $this->description = '';
        $this->assignedToUserId = null;
        $this->dueAt = null;
        $this->status = ActionItemStatus::Open->value;
        $this->resetValidation();
    }

    protected function parsedDueAt(): ?\DateTimeInterface
    {
        if (blank($this->dueAt)) {
            return null;
        }

        return TeamDateTime::fromDateTimeLocal($this->team(), $this->dueAt.'T00:00:00');
    }

    public function render()
    {
        $meeting = $this->meeting();
        $team = $this->team();
        $canManage = $this->canManageActionItems();
        $canViewAll = $canManage
            || ($this->readOnly && Auth::user()->can('create', [MeetingActionItem::class, $meeting]));

        $query = MeetingActionItem::query()
            ->with(['assignee', 'creator'])
            ->where('meeting_id', $this->meetingId)
            ->where('team_id', $this->teamId)
            ->orderBy('sort_order')
            ->orderBy('id');

        if (! $canViewAll) {
            $query->assignedTo(Auth::id());
        }

        $teamMembers = $canManage
            ? ($this->assigneeScope === 'meeting'
                ? app(MeetingActionItemAssigneeService::class)->eligibleUsers($meeting)
                : $team->users()->orderBy('name')->get())
            : collect();

        return view('afterburner-meetings::meetings.livewire.meeting-action-items', [
            'team' => $team,
            'meeting' => $meeting,
            'actionItems' => $query->get(),
            'canManageActionItems' => $canManage,
            'readOnly' => $this->readOnly,
            'embedded' => $this->embedded,
            'teamMembers' => $teamMembers,
            'statusOptions' => ActionItemStatus::cases(),
        ]);
    }
}
