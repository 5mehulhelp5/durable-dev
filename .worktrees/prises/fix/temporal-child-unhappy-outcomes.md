# fix/temporal-child-unhappy-outcomes

- **Scope**: #980, a child that fails to start, times out, is cancelled or is terminated settles its parent's await on Temporal, with the failure the journal backends raise. Reproduce first (a unit test over TemporalExecutionHistory), then fix.
- **Entries**: src/Bridge/Temporal/Worker/TemporalExecutionHistory.php, tests/unit/Bridge/Temporal/Worker/.
- **Overlap**: none known (#954 touches TemporalChildWorkflowRunner, not the history reader).
- **Session**: durable-01, 2026-10-07. Epic #970.
- **State**: in progress.
