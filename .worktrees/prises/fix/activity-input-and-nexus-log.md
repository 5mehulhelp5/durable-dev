# fix/activity-input-and-nexus-log

- **Scope**: #938: a non-JSON activity input fails the activity task (RespondActivityTaskFailed) instead of stopping the worker; TemporalNexusWorker logs a handler failure. Then #939 (log context ids) on the same branch or a follow-up.
- **Entries**: src/Bridge/Temporal/Worker, tests, UPGRADE.
- **Overlap**: none known
- **Session**: durable-8a (subagent), 2026-10-02.
- **State**: in progress.
