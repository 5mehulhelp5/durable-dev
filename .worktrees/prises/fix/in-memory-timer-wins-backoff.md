# fix/in-memory-timer-wins-backoff

- **Scope**: #653: in the in-memory runner, a timer due during an activity's backoff settles `any()` before the retry, as on Temporal.
- **Entries**: `src/Durable/` (ExecutionRuntime::runUntilIdle, InMemoryWorkflowRunner), tests, `UPGRADE.md`.
- **Overlap**: Starts after fix/in-memory-child-clock merges; same agent.
- **State**: in progress — subagent of durable-8a.
