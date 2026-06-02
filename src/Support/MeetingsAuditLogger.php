<?php

namespace Afterburner\Meetings\Support;

use Afterburner\Meetings\Models\CalendarEvent;
use Afterburner\Meetings\Models\Meeting;
use Afterburner\Meetings\Models\MeetingActionItem;
use Afterburner\Meetings\Models\MeetingAgendaItem;
use App\Models\User;
use App\Support\Audit\AuditLogger;

class MeetingsAuditLogger
{
    public const CATEGORY_MEETING = 'meeting';

    public const CATEGORY_CALENDAR = 'calendar';

    public static function meetingCreated(Meeting $meeting, User $actor): void
    {
        AuditLogger::log(
            category: self::CATEGORY_MEETING,
            eventName: 'meeting.created',
            auditable: $meeting,
            changes: AuditLogger::changesWithSummary(
                summary: "{$actor->name} created meeting \"{$meeting->title}\".",
                context: [
                    'meeting_id' => $meeting->id,
                    'title' => $meeting->title,
                    'type' => $meeting->type?->value ?? $meeting->type,
                    'status' => $meeting->status?->value ?? $meeting->status,
                ],
            ),
            metadata: ['actor_user_id' => $actor->id],
            teamId: $meeting->team_id,
            actionType: 'livewire',
        );
    }

    public static function meetingDeleted(Meeting $meeting, User $actor): void
    {
        AuditLogger::log(
            category: self::CATEGORY_MEETING,
            eventName: 'meeting.deleted',
            auditable: $meeting,
            changes: AuditLogger::changesWithSummary(
                summary: "{$actor->name} deleted meeting \"{$meeting->title}\".",
                context: [
                    'meeting_id' => $meeting->id,
                    'title' => $meeting->title,
                ],
            ),
            metadata: ['actor_user_id' => $actor->id],
            teamId: $meeting->team_id,
            actionType: 'livewire',
        );
    }

    public static function calendarEventSaved(CalendarEvent $event, User $actor, bool $wasCreated): void
    {
        AuditLogger::log(
            category: self::CATEGORY_CALENDAR,
            eventName: $wasCreated ? 'calendar.event.created' : 'calendar.event.updated',
            auditable: $event,
            changes: AuditLogger::changesWithSummary(
                summary: $wasCreated
                    ? "{$actor->name} created calendar event \"{$event->title}\"."
                    : "{$actor->name} updated calendar event \"{$event->title}\".",
                context: [
                    'event_id' => $event->id,
                    'title' => $event->title,
                    'starts_at' => $event->starts_at?->toIso8601String(),
                ],
            ),
            metadata: ['actor_user_id' => $actor->id],
            teamId: $event->team_id,
            actionType: 'livewire',
        );
    }

    public static function calendarEventDeleted(CalendarEvent $event, User $actor): void
    {
        AuditLogger::log(
            category: self::CATEGORY_CALENDAR,
            eventName: 'calendar.event.deleted',
            auditable: $event,
            changes: AuditLogger::changesWithSummary(
                summary: "{$actor->name} deleted calendar event \"{$event->title}\".",
                context: ['event_id' => $event->id, 'title' => $event->title],
            ),
            metadata: ['actor_user_id' => $actor->id],
            teamId: $event->team_id,
            actionType: 'livewire',
        );
    }

    public static function actionItemCreated(MeetingActionItem $item, User $actor): void
    {
        AuditLogger::log(
            category: self::CATEGORY_MEETING,
            eventName: 'meeting.action_item.created',
            auditable: $item,
            changes: AuditLogger::changesWithSummary(
                summary: "{$actor->name} added action item \"{$item->title}\".",
                context: [
                    'action_item_id' => $item->id,
                    'meeting_id' => $item->meeting_id,
                    'title' => $item->title,
                ],
            ),
            metadata: ['actor_user_id' => $actor->id],
            teamId: $item->meeting?->team_id,
            actionType: 'livewire',
        );
    }

    public static function actionItemDeleted(MeetingActionItem $item, User $actor): void
    {
        AuditLogger::log(
            category: self::CATEGORY_MEETING,
            eventName: 'meeting.action_item.deleted',
            auditable: $item,
            changes: AuditLogger::changesWithSummary(
                summary: "{$actor->name} deleted action item \"{$item->title}\".",
                context: [
                    'action_item_id' => $item->id,
                    'meeting_id' => $item->meeting_id,
                    'title' => $item->title,
                ],
            ),
            metadata: ['actor_user_id' => $actor->id],
            teamId: $item->meeting?->team_id,
            actionType: 'livewire',
        );
    }

    public static function meetingUpdated(Meeting $meeting, User $actor, array $fieldChanges = []): void
    {
        AuditLogger::log(
            category: self::CATEGORY_MEETING,
            eventName: 'meeting.updated',
            auditable: $meeting,
            changes: AuditLogger::changesWithSummary(
                summary: "{$actor->name} updated meeting \"{$meeting->title}\".",
                fieldChanges: $fieldChanges,
                context: ['meeting_id' => $meeting->id, 'title' => $meeting->title],
            ),
            metadata: ['actor_user_id' => $actor->id],
            teamId: $meeting->team_id,
            actionType: 'action_class',
        );
    }

    public static function meetingStatusChanged(Meeting $meeting, User $actor, string $from, string $to): void
    {
        AuditLogger::log(
            category: self::CATEGORY_MEETING,
            eventName: 'meeting.status.changed',
            auditable: $meeting,
            changes: AuditLogger::changesWithSummary(
                summary: "{$actor->name} changed meeting \"{$meeting->title}\" from {$from} to {$to}.",
                fieldChanges: ['status' => ['before' => $from, 'after' => $to]],
                context: ['meeting_id' => $meeting->id],
            ),
            metadata: ['actor_user_id' => $actor->id],
            teamId: $meeting->team_id,
            actionType: 'action_class',
        );
    }

    public static function agendaItemCreated(MeetingAgendaItem $item, User $actor): void
    {
        self::agendaItemEvent('meeting.agenda_item.created', $item, $actor, 'added agenda item');
    }

    public static function agendaItemUpdated(MeetingAgendaItem $item, User $actor): void
    {
        self::agendaItemEvent('meeting.agenda_item.updated', $item, $actor, 'updated agenda item');
    }

    public static function agendaItemDeleted(MeetingAgendaItem $item, User $actor): void
    {
        self::agendaItemEvent('meeting.agenda_item.deleted', $item, $actor, 'deleted agenda item');
    }

    public static function actionItemUpdated(MeetingActionItem $item, User $actor): void
    {
        AuditLogger::log(
            category: self::CATEGORY_MEETING,
            eventName: 'meeting.action_item.updated',
            auditable: $item,
            changes: AuditLogger::changesWithSummary(
                summary: "{$actor->name} updated action item \"{$item->title}\".",
                context: ['action_item_id' => $item->id, 'title' => $item->title],
            ),
            metadata: ['actor_user_id' => $actor->id],
            teamId: $item->meeting?->team_id,
            actionType: 'action_class',
        );
    }

    public static function minutesUpdated(Meeting $meeting, User $actor, bool $finalized): void
    {
        AuditLogger::log(
            category: self::CATEGORY_MEETING,
            eventName: $finalized ? 'meeting.minutes.finalized' : 'meeting.minutes.updated',
            auditable: $meeting,
            changes: AuditLogger::changesWithSummary(
                summary: $finalized
                    ? "{$actor->name} finalized minutes for \"{$meeting->title}\"."
                    : "{$actor->name} updated minutes for \"{$meeting->title}\".",
                context: ['meeting_id' => $meeting->id],
            ),
            metadata: ['actor_user_id' => $actor->id],
            teamId: $meeting->team_id,
            actionType: 'livewire',
        );
    }

    protected static function agendaItemEvent(string $eventName, MeetingAgendaItem $item, User $actor, string $verb): void
    {
        AuditLogger::log(
            category: self::CATEGORY_MEETING,
            eventName: $eventName,
            auditable: $item,
            changes: AuditLogger::changesWithSummary(
                summary: "{$actor->name} {$verb} \"{$item->title}\".",
                context: ['agenda_item_id' => $item->id, 'meeting_id' => $item->meeting_id, 'title' => $item->title],
            ),
            metadata: ['actor_user_id' => $actor->id],
            teamId: $item->meeting?->team_id,
            actionType: 'action_class',
        );
    }
}
