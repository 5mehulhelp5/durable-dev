# fix/temporal-workflow-id-injective

- **Scope**: #566. `WorkflowClient::workflowIdOf()` is lossy: `order/42`, `order 42` and `order-42`
  all map to `durable-order-42`. Per the user's decision on #566, an id that sanitisation leaves
  unchanged and that fits keeps `durable-<id>`. Any other id becomes a sanitised prefix, a marker,
  and a hash of the whole id. Starts always use the new mapping. Lookups (signal, update, query,
  history read, `findRun()`) fall back to the old mapping for one release, and only accept a run
  whose `durableExecutionId` memo matches.
- **Not in scope**:
  - `TemporalConnection::journalWorkflowId()`, which belongs to the journal store bob deletes in
    #356.
  - Nexus ids, which come from the handler's response, not from an execution id.
  - Child workflow ids: they already use the raw child execution id, which is injective, and stay
    on it (agreed with durable-30), so no replay risk.
- **Entries**: `src/Bridge/Temporal/WorkflowClient.php`, the signal and update handlers,
  `src/Bridge/Temporal/Store/TemporalReadThroughEventStore.php`,
  `src/Bridge/Temporal/Store/TemporalWorkflowRunCatalog.php` (`findRun()`, jane's, coordinated), their
  tests, the Temporal integration tests, `UPGRADE.md`.
- **State**: taken — alice. Reviewer: sirius. Only durable-30 merges.
