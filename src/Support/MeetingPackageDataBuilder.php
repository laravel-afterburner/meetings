<?php

namespace Afterburner\Meetings\Support;

use Afterburner\Meetings\Contracts\MeetingMinutesAttendanceSummaryProvider;
use Afterburner\Meetings\Enums\AttendanceStatus;
use Afterburner\Meetings\Enums\MeetingStatus;
use Afterburner\Meetings\Models\Meeting;

class MeetingPackageDataBuilder
{
    public function __construct(
        protected MeetingMinutesSectionBuilder $sectionBuilder,
        protected MeetingMinutesAttendanceSummaryProvider $attendanceSummaryProvider,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(Meeting $meeting): array
    {
        $meeting->loadMissing(array_merge([
            'team',
            'minutesFinalizedBy',
            'attendances.user',
            'actionItems.assignee',
            'agendaItems',
        ], DocumentsIntegration::isAvailable() ? ['linkedDocuments'] : []));

        $team = $meeting->team;

        return [
            'team' => $team,
            'meeting' => $meeting,
            'teamName' => $team->name,
            'meetingTitle' => $meeting->title,
            'meetingTypeLabel' => $meeting->type->label(),
            'meetingStatusLabel' => $meeting->status->label(),
            'scheduledDisplay' => TeamDateTime::format($team, $meeting->scheduled_at),
            'location' => $meeting->location,
            'virtualLink' => $meeting->virtual_link,
            'agendaNotes' => $meeting->agenda_notes,
            'minutes' => $meeting->minutes,
            'minutesFinalizedDisplay' => $meeting->minutes_finalized_at
                ? TeamDateTime::format($team, $meeting->minutes_finalized_at)
                : null,
            'minutesFinalizedByName' => $meeting->minutesFinalizedBy?->name,
            'attendanceSummaryLines' => $this->attendanceSummaryProvider->summaryLines($meeting),
            'attendanceRecords' => $this->attendanceRecords($meeting),
            'agendaSection' => $this->sectionBuilder->build($meeting, 'agenda'),
            'minutesSection' => filled($meeting->minutes) ? $meeting->minutes : null,
            'actionItemsSection' => $this->sectionBuilder->build($meeting, 'action_items'),
            'quorumSection' => $this->sectionBuilder->build($meeting, 'quorum'),
            'resolutionsSection' => $this->sectionBuilder->build($meeting, 'resolutions'),
            'linkedDocuments' => DocumentsIntegration::linkedDocumentSummariesFor($meeting),
            'logoUrl' => method_exists($team, 'getLogoUrl') ? $team->getLogoUrl() : null,
            'generatedAt' => TeamDateTime::format($team, now()),
            'isCompleted' => $meeting->status === MeetingStatus::Completed,
        ];
    }

    /**
     * @return list<array{label: string, status: string}>
     */
    protected function attendanceRecords(Meeting $meeting): array
    {
        return $meeting->attendances
            ->sortBy(fn ($attendance) => $attendance->user?->name ?? '')
            ->map(function ($attendance) {
                return [
                    'label' => $attendance->user?->name ?? 'Unknown member',
                    'status' => match ($attendance->status) {
                        AttendanceStatus::Present => 'Present',
                        AttendanceStatus::EligibleNotPresent => 'Absent',
                        default => $attendance->status->label(),
                    },
                ];
            })
            ->values()
            ->all();
    }
}
