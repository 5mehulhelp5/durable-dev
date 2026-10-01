# feat/execution-id-engine

- **Scope**: #682 next part: WorkflowFiberDriver::run(), PassEventStore::open(), ExecutionEngine::start()/resume() and EventStoreHistorySource take an ExecutionId; AnEmptyExecutionIdIsRefusedTest moves to the entry points that still take a string.
- **Entries**: src/Durable engine and runtime, src/Bridge/*, src/DurableRector, UPGRADE.
- **Overlap**: fix/poll-for-completion-failure and fix/memo-non-string-fails: merge main often.
- **Session**: durable-8a (subagent), 2026-10-01.
- **State**: in progress.
