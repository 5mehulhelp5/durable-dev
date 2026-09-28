# test/temporal-replay-conformance

- **Scope**: #326, what #502 left: the replay tier of DUR041 on Temporal. The conformance workflow runs
  on a server and inline on the reference, and every `WorkflowHistorySourceInterface` lookup read
  through `TemporalExecutionHistory` must agree; plus the DUR041 docblock in
  `EventStoreReplayConformanceTestCase.php`.
- **Entries**: `src/Durable/Testing/ConformanceWorkflow.php`, `src/Durable/Testing/EventStoreReplayConformanceTestCase.php`,
  `tests/integration/Temporal/TemporalHistoryReplayConformanceTest.php`, `tests/integration/Temporal/Fixtures/IntegrationWorkflows.php`.
- **State**: in progress — bob. Reviewer: sirius.
