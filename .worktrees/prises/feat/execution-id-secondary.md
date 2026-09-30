# feat/execution-id-secondary

- **Scope**: #682 part (c): the secondary ids (child, continue-as-new next id, sourceParentExecutionId), WorkflowEnvironment::executionId(), the string constructors of EventStoreCommandBuffer and TemporalEventConverter.
- **Entries**: src/Durable, src/Bridge/*, src/DurableRector, UPGRADE.
- **Overlap**: fix/profiler-labels-and-memo-id touches the Temporal memo reading (#799): merge main often.
- **Session**: durable-8a (subagent), 2026-09-30.
- **State**: in progress.
