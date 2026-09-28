# refactor/psr20-clock

- **Scope**: #617: the core reads time through a PSR-20 ClockInterface instead of a closure (e.g. ExecutionRuntime).
- **Entries**: `src/Durable/` and the host wiring that passes a clock (bundle, Laravel, Magento), their tests, `UPGRADE.md`.
- **Overlap**: none open after #616 (merged).
- **State**: in progress — subagent of durable-97.
