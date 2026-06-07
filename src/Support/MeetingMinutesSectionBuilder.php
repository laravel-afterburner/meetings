<?php

namespace Afterburner\Meetings\Support;

use Afterburner\Meetings\Contracts\MeetingMinutesAttendanceSummaryProvider;
use Afterburner\Meetings\Models\Meeting;
use Afterburner\Voting\Services\BallotTallyService;
use Afterburner\Voting\Services\QuorumService;
use App\Support\TeamDateTime;

class MeetingMinutesSectionBuilder
{
    public function __construct(
        protected MinutesTemplate $template,
        protected MeetingMinutesAttendanceSummaryProvider $attendanceSummaryProvider,
    ) {}

    public function build(Meeting $meeting, string $sectionKey): ?string
    {
        if (! $this->template->isEnabled($sectionKey)) {
            return null;
        }

        return match ($sectionKey) {
            'attendance_summary' => $this->buildAttendanceSummary($meeting),
            'quorum' => $this->buildQuorum($meeting),
            'resolutions' => $this->buildResolutions($meeting),
            'action_items' => $this->buildActionItems($meeting),
            'agenda' => $this->buildAgenda($meeting),
            default => null,
        };
    }

    public function buildAll(Meeting $meeting): string
    {
        $sections = [];

        foreach (array_keys($this->template->sections()) as $sectionKey) {
            $content = $this->build($meeting, $sectionKey);

            if (filled($content)) {
                $sections[] = $content;
            }
        }

        if ($sections === []) {
            return '';
        }

        $header = $this->buildHeader($meeting);

        return filled($header)
            ? $header."\n\n".implode("\n\n", $sections)
            : implode("\n\n", $sections);
    }

    protected function buildHeader(Meeting $meeting): string
    {
        $meeting->loadMissing('team');
        $lines = [
            'MINUTES — '.$meeting->title,
            $meeting->team->name,
        ];

        if ($meeting->scheduled_at) {
            $lines[] = 'Date: '.TeamDateTime::format($meeting->team, $meeting->scheduled_at);
        }

        return implode("\n", $lines);
    }

    protected function buildAttendanceSummary(Meeting $meeting): string
    {
        $lines = $this->attendanceSummaryProvider->summaryLines($meeting);

        return strtoupper($this->template->label('attendance_summary'))."\n".implode("\n", $lines);
    }

    protected function buildQuorum(Meeting $meeting): ?string
    {
        if (! VotingIntegration::isEnabled()) {
            return null;
        }

        $ballots = $meeting->linkedBallots();

        if ($ballots->isEmpty()) {
            return null;
        }

        $quorumService = app(QuorumService::class);
        $lines = [];

        foreach ($ballots as $ballot) {
            $quorum = $quorumService->calculate($ballot);

            if (! $quorum['configured']) {
                $lines[] = $ballot->title.': Quorum not configured.';

                continue;
            }

            $status = $quorum['met'] ? 'Quorum met' : 'Quorum not met';
            $lines[] = sprintf(
                '%s: %s (%.1f%% of %.1f%% required; %d of %d eligible)',
                $ballot->title,
                $status,
                $quorum['percent'],
                $quorum['required'],
                $quorum['cast'],
                $quorum['eligible'],
            );
        }

        if ($lines === []) {
            return null;
        }

        return strtoupper($this->template->label('quorum'))."\n".implode("\n", $lines);
    }

    protected function buildResolutions(Meeting $meeting): ?string
    {
        if (! VotingIntegration::isEnabled()) {
            return null;
        }

        $ballots = $meeting->linkedBallots();

        if ($ballots->isEmpty()) {
            return null;
        }

        $tallyService = app(BallotTallyService::class);
        $blocks = [];

        foreach ($ballots as $ballot) {
            $blockLines = [$ballot->title];

            if ($ballot->description) {
                $blockLines[] = $ballot->description;
            }

            if ($tallyService->canViewTally($ballot)) {
                $tally = $tallyService->tally($ballot);

                foreach ($tally['options'] as $option) {
                    $blockLines[] = sprintf(
                        '%s: %s (%s%%)',
                        $option['label'],
                        $this->formatVoteCount($option['count']),
                        number_format($option['percentage'], 1),
                    );
                }

                $blockLines[] = 'Total votes: '.$this->formatVoteCount($tally['total_votes']);
            } else {
                $blockLines[] = 'Results not yet available.';
            }

            $blocks[] = implode("\n", $blockLines);
        }

        return strtoupper($this->template->label('resolutions'))."\n\n".implode("\n\n", $blocks);
    }

    protected function buildActionItems(Meeting $meeting): ?string
    {
        $meeting->loadMissing(['actionItems.assignee']);

        if ($meeting->actionItems->isEmpty()) {
            return null;
        }

        $lines = [];

        foreach ($meeting->actionItems as $item) {
            $line = '- '.$item->title;

            if ($item->assignee) {
                $line .= ' (Assigned to: '.$item->assignee->name.')';
            }

            if ($item->due_at) {
                $line .= ' — Due: '.TeamDateTime::format($meeting->team, $item->due_at);
            }

            $line .= ' ['.$item->status->label().']';

            $lines[] = $line;
        }

        return strtoupper($this->template->label('action_items'))."\n".implode("\n", $lines);
    }

    protected function buildAgenda(Meeting $meeting): ?string
    {
        $meeting->loadMissing(['agendaItems.reference']);

        if ($meeting->agendaItems->isEmpty()) {
            return null;
        }

        $lines = [];

        foreach ($meeting->agendaItems as $item) {
            $line = '- '.$item->title;

            if ($item->section) {
                $line .= ' ['.$item->section->label().']';
            }

            $lines[] = $line;

            $summary = $item->displaySummary();

            if (filled($summary)) {
                $lines[] = '  '.$summary;
            }
        }

        return strtoupper($this->template->label('agenda'))."\n".implode("\n", $lines);
    }

    protected function formatVoteCount(float $count): string
    {
        if (floor($count) === $count) {
            return (string) (int) $count;
        }

        return number_format($count, 1);
    }
}
