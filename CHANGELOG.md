# Changelog

All notable changes to `laravel-afterburner/meetings` are documented in this file.

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
