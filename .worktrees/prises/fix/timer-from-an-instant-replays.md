# fix/timer-from-an-instant-replays

- **Work item**: a timer or deadline given as a `DateTimeInterface` is converted to a `Duration`
  before the slot is consulted, and `Duration::until()` throws for any past instant — so the resume
  that follows the timer's own firing fails the execution. Issue #314, first task of milestone 1.
- **Inputs**: `src/Durable/WorkflowEnvironment.php`, `src/Durable/Duration.php`,
  `src/Durable/ExecutionContext.php`, tests under `tests/unit/Durable/`. Failing test first.
- **State**: in progress.
