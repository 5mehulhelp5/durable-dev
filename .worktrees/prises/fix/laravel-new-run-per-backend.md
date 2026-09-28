# fix/laravel-new-run-per-backend

- **Work item**: #603. On Laravel, `WorkflowResumeDispatcher::dispatchNewWorkflowRun()` silently does
  nothing on the `memory` and `temporal` backends (`NullWorkflowResumeDispatcher`).
- **Decision** (the user's, on the issue): `temporal` binds `TemporalWorkflowResumeDispatcher`, as
  Symfony does; `memory` drives the run in the caller's process (`InProcessWorkflowResumeDispatcher`).
- **Entries**: `src/DurableLaravel/DurableServiceProvider.php`, a memory dispatcher, the Laravel
  README, getting-started, `context7.json` (#600's rule 3).
- **State**: anna. In review as PR #630 (jack).
