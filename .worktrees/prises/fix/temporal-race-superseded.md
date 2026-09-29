# fix/temporal-race-superseded

- **Scope**: #681: on Temporal, an activity cancelled as the loser of `any()` replays as unsettled, and RequestCancelActivityTask is not re-sent on replay.
- **Entries**: `src/Bridge/Temporal/` (TemporalExecutionHistory, the command buffer), `tests/integration/Temporal/`, `UPGRADE.md`.
- **Overlap**: feat/execution-id-beyond-ports may change the same signatures.
- **State**: in progress — subagent of durable-8a.
