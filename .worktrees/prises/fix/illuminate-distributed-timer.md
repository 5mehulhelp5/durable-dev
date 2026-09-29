# fix/illuminate-distributed-timer

- **Scope**: #726: confirm, then fix, whether a due timer on the Laravel illuminate backend is dispatched as a plain resume and never fires.
- **Entries**: `src/DurableLaravel/`, `src/Bridge/Illuminate/`, tests.
- **Overlap**: feat/laravel-activity-handlers (#713) touches DurableServiceProvider.
- **State**: in progress — subagent of durable-8a.
