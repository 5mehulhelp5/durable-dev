# refactor/explicit-backend-refusals

- **Scope**: #331, the user's decision of 2026-09-28: one port, with explicit refusals.
  1. DUR051, the ADR. It is a separate PR, and the user approves its text.
  2. The implementation, with TDD. Every `WorkflowCommandBufferInterface` method a backend cannot
     honour throws a named refusal instead of an empty body, and `NullEventStore` throws on
     `readStream()` or goes. An UPGRADE entry is added if a caller could rely on the old behaviour.
- **Entries**:
  - `documentation/adr/DUR051-*` and `documentation/INDEX.md`, the ADR PR only;
  - `src/Durable/Port/WorkflowCommandBufferInterface.php`;
  - `src/Bridge/Temporal/Worker/TemporalWorkflowCommandBuffer.php` (`recordUpdateHandled()`,
    `completeChildWorkflow()`, `failChildWorkflow()`);
  - `src/Durable/Store/NullEventStore.php`;
  - `src/Bridge/Temporal/Worker/WorkflowTaskRunner.php` (the runtime's store);
  - a new core exception, `UPGRADE.md`, and their tests.
- **State**: ADR draft in progress — jane. Reviewer: sirius. Then #523, under its own prise.
