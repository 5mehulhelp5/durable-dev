# refactor/history-source-value-objects-bob

- **Scope**: #325, shape fixes only (user decision, 2026-09-24): `WorkflowHistorySourceInterface`
  returns readonly value objects under `Port\History\` instead of `array{…}` shapes, the fabricated
  timer `scheduledAt` goes, the side-effect result is wrapped. `ExecutionId` type-hints and the Rector
  rule stay for #269. Then #326 (the Temporal replay tier), which waits on it.
- **Entries**: `src/Durable/Port/WorkflowHistorySourceInterface.php`, `src/Durable/Port/History/`,
  `src/Durable/Store/EventStoreHistorySource.php`, `src/Bridge/Temporal/Worker/TemporalExecutionHistory.php`,
  `src/Durable/ExecutionContext.php`, `src/Durable/Worker/WorkflowFiberDriver.php`, the replay conformance
  suite, their tests, `UPGRADE.md`.
- **State**: in review — PR #635, bob (took over from durable-50; its work is archived under
  `archive/history-source-value-objects*`). Reviewer: sirius.
