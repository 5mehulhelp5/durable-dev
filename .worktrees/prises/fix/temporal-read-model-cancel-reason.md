# fix/temporal-read-model-cancel-reason

- **Scope**: #701: TemporalEventConverter takes a cancelled activity's reason from the history (race_superseded vs workflow_cancelled); the replay conformance workflow gains an any(activity, timer) race.
- **Entries**: `src/Bridge/Temporal/Store/TemporalEventConverter.php`, `src/Durable/Testing/` (conformance workflow and cases), tests, `UPGRADE.md` if the read model changes.
- **Overlap**: feat/execution-id-beyond-ports (#703) touches the Temporal bridge's signatures.
- **State**: in progress — subagent of durable-8a.
