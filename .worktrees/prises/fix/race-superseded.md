# fix/race-superseded

- **Scope**: #678: a timer that wins `any()` against a retrying activity no longer makes the next resume fail with race_superseded.
- **Entries**: `src/Durable/` (EventStoreHistorySource, ContextActivityScheduler, the engine's race handling), tests, `UPGRADE.md` if replay rules change.
- **Overlap**: fix/in-memory-timer-wins-backoff (#677) waits on this one; feat/execution-id-ports touches the same files' signatures.
- **State**: in progress — subagent of durable-8a.
