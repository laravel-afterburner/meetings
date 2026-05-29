# Changelog

All notable changes to `laravel-afterburner/meetings` are documented in this file.

## [1.2.0] - 2026-05-28

### Added

- Configurable `minutes_template` sections: attendance summary, quorum, resolutions, and action items
- `BuildMeetingMinutesDraft` action to merge live meeting data into an editable minutes draft
- `MeetingMinutesSectionBuilder` with pluggable `MeetingMinutesAttendanceSummaryProvider`
- Meeting show UI: generate draft from meeting data and per-section insert buttons
- Package tests for minutes draft generation with ballot tallies and action items

## [1.1.0] - 2026-05-27

### Added

- Meeting action items: meeting-scoped follow-up tasks with assignee, due date, and status tracking
- Actions: `CreateMeetingActionItem`, `UpdateMeetingActionItem`, `CompleteMeetingActionItem`, `DeleteMeetingActionItem`
- `MeetingActionItemPolicy` with `manage_meetings` for create/edit/delete; assignees can update status on own items
- `MeetingActionItems` Livewire component on meeting show (secretary view + assignee-filtered view)
- Optional overdue action-item badge on meetings index
- `MeetingActionItemAssigned` event and stub `NotifyMeetingActionItemAssignee` listener for host-app notifications

## [1.0.0] - 2026-05-27

### Added

- Team-scoped meetings (AGM, council, special) with draft/create/schedule flow
- Meeting attendance keyed to morph `voter_unit` (host app supplies units)
- Optional documents integration for agenda materials (`meeting-documents` Livewire)
- Optional voting integration for linked ballots (`meeting-ballots` Livewire)
- Meeting minutes updates and audience targeting by role
- `manage_meetings` permission seeder and `afterburner:meetings:install` command
- Navigation registration, policies, and audit skip-route merge
- `MeetingScheduled` notification stub and `NotifyMeetingAudience` listener
