# refactor/delete-journal-workflow-leftovers

- **Scope**: #594. Delete `JournalStateResolver` and its test, and `TemporalConnection::journalWorkflowId()`,
  `signalAppend`, `DEFAULT_WORKFLOW_TYPE`, the `workflowType` parameter and the `workflow_type` DSN key
  (the user's decisions), with an UPGRADE entry.
- **Entries**: `src/Bridge/Temporal/Journal/`, `src/Bridge/Temporal/TemporalConnection.php`, their tests,
  `UPGRADE.md`.
- **State**: in review, PR #612 — elsa. Reviewer: sirius.
