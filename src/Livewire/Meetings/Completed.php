<?php

namespace Afterburner\Meetings\Livewire\Meetings;

use Afterburner\Meetings\Actions\CompileMeetingPackage;
use Afterburner\Meetings\Enums\MeetingStatus;
use Afterburner\Meetings\Models\Meeting;
use Afterburner\Meetings\Models\MeetingActionItem;
use Afterburner\Meetings\Support\DocumentsIntegration;
use Afterburner\Meetings\Support\MeetingOpenItemsChecker;
use Afterburner\Meetings\Support\MeetingPackagePdfExporter;
use Afterburner\Meetings\Support\TeamDateTime;
use App\Models\Team;
use App\Traits\InteractsWithBanner;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Completed extends Component
{
    use InteractsWithBanner;

    public int $teamId;

    public int $meetingId;

    public bool $showCompileConfirmModal = false;

    public function mount(Team $team, Meeting $meeting): void
    {
        if ($meeting->team_id !== $team->id) {
            abort(404);
        }

        abort_unless(Auth::user()->can('reviseAfterCompletion', $meeting), 403);

        if ($meeting->status !== MeetingStatus::Completed) {
            if ($meeting->status === MeetingStatus::InProgress) {
                $this->redirectRoute('teams.meetings.in-progress', ['team' => $team, 'meeting' => $meeting]);

                return;
            }

            $this->redirectRoute('teams.meetings.show', ['team' => $team, 'meeting' => $meeting]);

            return;
        }

        $this->teamId = $team->id;
        $this->meetingId = $meeting->id;
    }

    public function requestCompilePackage(): void
    {
        abort_unless($this->canCompilePackage(), 403);

        if (count(app(MeetingOpenItemsChecker::class)->warnings($this->meeting())) > 0) {
            $this->showCompileConfirmModal = true;

            return;
        }

        $this->compilePackage();
    }

    public function cancelCompilePackage(): void
    {
        $this->showCompileConfirmModal = false;
    }

    public function confirmCompilePackage(): void
    {
        $this->showCompileConfirmModal = false;
        $this->compilePackage();
    }

    public function compilePackage(): void
    {
        abort_unless($this->canCompilePackage(), 403);

        try {
            $document = app(CompileMeetingPackage::class)->execute(
                $this->meeting(),
                Auth::user(),
            );

            $this->banner(__('Meeting package saved to the :folder folder as :name.', [
                'folder' => config('afterburner-meetings.documents_package.folder_name', 'Meetings'),
                'name' => $document->name,
            ]));
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

    protected function canCompilePackage(): bool
    {
        return Auth::user()->can('compilePackage', $this->meeting());
    }

    public function render()
    {
        $team = Team::query()->findOrFail($this->teamId);
        $meeting = $this->meeting()->load('minutesFinalizedBy');
        $openItemsChecker = app(MeetingOpenItemsChecker::class);
        $hasUnfinalizedMinutes = $meeting->minutes_finalized_at === null && filled($meeting->minutes);

        return view('afterburner-meetings::meetings.livewire.completed', [
            'team' => $team,
            'meeting' => $meeting,
            'scheduledDisplay' => TeamDateTime::formatDisplay($team, $meeting->scheduled_at),
            'canManageActionItems' => Auth::user()->can('create', [MeetingActionItem::class, $meeting]),
            'canRecordAttendance' => Auth::user()->can('manageAttendance', $meeting),
            'canRecordMinutes' => Auth::user()->can('recordMinutes', $meeting),
            'canEditMeeting' => Auth::user()->can('update', $meeting),
            'canCompilePackage' => $this->canCompilePackage(),
            'packagePdfAvailable' => app(MeetingPackagePdfExporter::class)->isAvailable(),
            'documentsEnabled' => DocumentsIntegration::isEnabled(),
            'compileWarnings' => $openItemsChecker->warnings($meeting),
            'hasOpenItems' => $openItemsChecker->hasOpenItems($meeting),
            'hasUnfinalizedMinutes' => $hasUnfinalizedMinutes,
        ]);
    }
}
