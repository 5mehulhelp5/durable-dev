# fix/child-id-reuse-policy

- **Scope**: #950, refuse a child id whose run has not finished under every reuse policy, whichever parent started it; async runner refuses a missing metadata store; wording.
- **Entries**: src/Durable/ExecutionContext.php, src/Durable/ChildWorkflowRunner.php, src/Durable/Port/ChildWorkflowRunnerInterface.php, UPGRADE.md.
- **Overlap**: none known
- **Session**: w5, 2026-10-05.
- **State**: in review (#954).
