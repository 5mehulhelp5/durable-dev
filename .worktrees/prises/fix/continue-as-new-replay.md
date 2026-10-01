# fix/continue-as-new-replay

- **Scope**: #878 and #881: a replayed continue-as-new reuses its next id, and a crash mid continue-as-new no longer loses the next run.
- **Entries**: src/Durable (EventStoreWorkflowLifecycle, ResumeWorkflowHandler), tests, UPGRADE.
- **State**: in progress.
