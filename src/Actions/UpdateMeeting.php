<?php

namespace Afterburner\Meetings\Actions;

use Afterburner\Meetings\Enums\MeetingStatus;
use Afterburner\Meetings\Enums\MeetingType;
use Afterburner\Meetings\Events\MeetingScheduled;
use Afterburner\Meetings\Exceptions\MeetingsException;
use Afterburner\Meetings\Listeners\NotifyMeetingAudience;
use Afterburner\Meetings\Models\Meeting;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class UpdateMeeting
{
    public function execute(
        Meeting $meeting,
        User $user,
        string $title,
        MeetingType $type,
        MeetingStatus $status,
        ?string $location = null,
        ?string $virtualLink = null,
        ?string $agendaNotes = null,
        ?\DateTimeInterface $scheduledAt = null,
        ?array $targetRoleSlugs = null,
    ): Meeting {
        if ($meeting->isEditable()) {
            Gate::forUser($user)->authorize('update', $meeting);
        } elseif ($status === $meeting->status && $meeting->status === MeetingStatus::Completed) {
            Gate::forUser($user)->authorize('update', $meeting);
        } elseif ($status === $meeting->status) {
            throw new MeetingsException('This meeting can no longer be edited.');
        } elseif ($status === MeetingStatus::InProgress && $meeting->status === MeetingStatus::Scheduled) {
            Gate::forUser($user)->authorize('start', $meeting);
        } elseif ($status === MeetingStatus::Completed && $meeting->status === MeetingStatus::InProgress) {
            Gate::forUser($user)->authorize('complete', $meeting);
        } else {
            Gate::forUser($user)->authorize('manageAttendance', $meeting);
        }

        $payload = ['status' => $status];

        if ($meeting->isEditable()) {
            $payload += [
                'title' => $title,
                'type' => $type,
                'location' => $location,
                'virtual_link' => $virtualLink,
                'agenda_notes' => $agendaNotes,
                'scheduled_at' => $scheduledAt,
            ];

            if ($targetRoleSlugs !== null) {
                $payload['target_role_slugs'] = $targetRoleSlugs;
            }
        } elseif ($meeting->status === MeetingStatus::Completed && $status === MeetingStatus::Completed) {
            $payload += [
                'title' => $title,
                'location' => $location,
                'virtual_link' => $virtualLink,
                'agenda_notes' => $agendaNotes,
                'scheduled_at' => $scheduledAt,
            ];
        }

        $becameScheduled = $status === MeetingStatus::Scheduled
            && $meeting->status !== MeetingStatus::Scheduled;

        $meeting->update($payload);

        $meeting = $meeting->fresh();

        if ($becameScheduled) {
            $event = new MeetingScheduled($meeting);
            app(NotifyMeetingAudience::class)->handle($event);
            MeetingScheduled::dispatch($meeting);
        }

        return $meeting;
    }
}
