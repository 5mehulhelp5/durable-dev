# fix/refuse-continue-as-new-queue-and-timeouts

- **Scope**: #977, continueAsNew queue and timeouts are refused by name on the journal backends. Epic #970.
- **Entries**: src/Durable/ContinueAsNewOptions*, src/Durable/Store/EventStoreWorkflowLifecycle.php, UPGRADE.md.
- **Overlap**: UPGRADE.md at the end of Unreleased; the sibling slices of the same epic.
- **Session**: durable-01 (worker), 2026-10-07.
- **State**: in progress.
