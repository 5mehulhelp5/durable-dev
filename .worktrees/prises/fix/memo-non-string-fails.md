# fix/memo-non-string-fails

- **Scope**: #890: a started memo whose execution id is present but not a non-empty string fails instead of falling back to the workflow id.
- **Entries**: src/Bridge/Temporal (JournalExecutionIdResolver and its callers), tests, UPGRADE.
- **Overlap**: none known
- **Session**: durable-8a (subagent), 2026-10-01.
- **State**: in progress.
