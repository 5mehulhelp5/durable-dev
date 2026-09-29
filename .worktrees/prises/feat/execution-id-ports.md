# feat/execution-id-ports

- **Scope**: #638: `ExecutionId` instead of `string` across the ports, with a Rector rule that migrates call sites (UPGRADE entry).
- **Entries**: `src/Durable/` ports and their implementations in the bridges and hosts, `src/DurableRector/`, tests, `UPGRADE.md`.
- **Overlap**: fix/in-memory-child-clock and fix/in-memory-timer-wins-backoff touch the in-memory runner; this branch only changes signatures there.
- **State**: in progress — subagent of durable-8a.
