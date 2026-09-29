# feat/execution-id-beyond-ports

- **Scope**: #682: `ExecutionId` in the interfaces and boundaries #638 left on string (projections, observers, activity transport, coordinator, lifecycle, timers, command buffer, FencedEventStore, Event::executionId(), WorkflowRunDescription, the listed helpers), with Rector and UPGRADE.
- **Entries**: `src/Durable/` and its implementations in bridges and hosts, `src/DurableRector/`, tests, `UPGRADE.md`.
- **Overlap**: fix/temporal-race-superseded touches the Temporal history reader; keep edits there to signatures.
- **State**: in progress — subagent of durable-8a.
