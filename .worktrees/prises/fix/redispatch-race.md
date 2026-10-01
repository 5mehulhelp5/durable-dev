# fix/redispatch-race

- **Scope**: #918: a re-dispatch of the next run can race its completion and reopen it.
- **Entries**: src/Durable (continue-as-new re-dispatch, ResumeWorkflowHandler), bridges if the fix needs a store guard, tests, UPGRADE if behaviour changes.
- **Overlap**: none known
- **Session**: durable-8a (subagent), 2026-10-02.
- **State**: in progress.
