# refactor/delete-journal-workflow-leftovers

- **Scope**: #594. Delete `JournalStateResolver` and its test, and `TemporalConnection::journalWorkflowId()`,
  `signalAppend` and `DEFAULT_WORKFLOW_TYPE` (the user's decision), with an UPGRADE entry.
- **Entries**: `src/Bridge/Temporal/Journal/`, `src/Bridge/Temporal/TemporalConnection.php`, their tests,
  `UPGRADE.md`.
- **State**: taken, starts once #589 is on main — elsa. Reviewer: sirius.
