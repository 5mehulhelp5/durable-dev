# fix/activity-schedule-timeouts

- **Scope**: #978, a retry whose delay runs past scheduleToClose fails at once with the schedule-to-close timeout; scheduleToStart per attempt. Epic #970.
- **Entries**: src/Durable/Worker/ActivityMessageProcessor.php, src/Durable/Transport/ActivityMessage.php, tests/unit/Durable/Worker/.
- **Overlap**: UPGRADE.md at the end of Unreleased; the sibling slices of the same epic.
- **Session**: durable-01 (worker), 2026-10-07.
- **State**: in progress.
