# fix/query-cancelled-timer

- **Scope**: `WorkflowQueryEvaluator::hasPendingTimer()` counts a cancelled timer as pending; read the pending timers through `PendingTimers::of()`, which already drops `TimerCancelled`. Failing test first.
- **Entries**: `src/Durable/Query/WorkflowQueryEvaluator.php`, one new test.
- **Overlap**: #793 adds a private constructor to the same class, on other lines.
- **State**: in progress.
