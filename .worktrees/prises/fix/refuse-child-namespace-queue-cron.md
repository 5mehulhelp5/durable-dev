# fix/refuse-child-namespace-queue-cron

- **Scope**: #977, a child's namespace, task queue and cron are refused by name on the journal backends. Epic #970.
- **Entries**: src/Durable/ChildWorkflowOptions.php, src/Durable/Store/EventStoreCommandBuffer.php, UPGRADE.md.
- **Overlap**: UPGRADE.md at the end of Unreleased; the sibling slices of the same epic.
- **Session**: durable-01 (worker), 2026-10-07.
- **State**: in progress.
