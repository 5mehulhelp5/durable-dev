# fix/laravel-new-run-per-backend

- **Work item**: #603. On Laravel, `WorkflowResumeDispatcher::dispatchNewWorkflowRun()` silently does
  nothing on the `memory` and `temporal` backends (`NullWorkflowResumeDispatcher`).
- **Proposal** (on the issue): `temporal` binds `TemporalWorkflowResumeDispatcher`, as Symfony does;
  `memory` refuses `dispatchNewWorkflowRun()` by name (DUR051's `UnsupportedByBackendException`).
- **Entries**: `src/DurableLaravel/DurableServiceProvider.php`, a memory dispatcher, the Laravel
  README, getting-started, `context7.json` (#600's rule 3).
- **State**: taken by anna, 2026-09-28. Temporal half first; the memory half waits on the reviewer's
  or the user's word. Reviewer: jack.
