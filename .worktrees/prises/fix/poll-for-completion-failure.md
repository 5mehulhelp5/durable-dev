# fix/poll-for-completion-failure

- **Scope**: #872: pollForCompletion() reports a failed Temporal workflow with the workflow's own exception (or DurableWorkflowAlgorithmFailureException), the original failure as previous, as the journal backends do.
- **Entries**: src/Bridge/Temporal client, tests, docs EN/FR parity note, UPGRADE if the thrown type changes.
- **Overlap**: fix/memo-non-string-fails touches the same bridge: merge main often.
- **Session**: durable-8a (subagent), 2026-10-01.
- **State**: in progress.
