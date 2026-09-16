# fix/unknown-suspension-fails-loudly

- **Work item**: a fiber that suspends with anything but an Awaitable hits a `break`, `run()`
  returns `null` with no lifecycle callback, and the handler takes that `null` for a result and
  marks the run completed — no `ExecutionCompleted` in the journal, a `null` result to a linked
  parent. Issue #315, milestone 1. Failing test first.
- **Inputs**: `src/Durable/Worker/WorkflowFiberDriver.php`, `src/Durable/Handler/ResumeWorkflowHandler.php`,
  `tests/unit/Durable/Worker/WorkflowFiberDriverTest.php`.
- **State**: in progress.
