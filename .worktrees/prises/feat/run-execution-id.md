# feat/run-execution-id

- **Scope**: #514, option (A), the user's decision. `WorkflowRunDescription` gains `executionId`, the
  id the application started the run with, and `runId` keeps the backend's own id. Four slices:
  - a. The field, filled on Temporal from the `durableExecutionId` memo. Conformance: "a run is
    found by the id the application started it with".
  - b. Temporal `findRun($executionId)` through `DescribeWorkflowExecution` on `workflowId()`.
  - c. The Sylius plugin, the Symfony bench and Magento link and select by `executionId`.
  - d. The Temporal worker records the wait in a `durableWaitingOn` memo, the catalog reads it,
    and the profiler gets the Temporal catalog back.
- **Entries**:
  - `src/Durable/Observation/WorkflowRunDescription.php`;
  - `TemporalWorkflowRunCatalog::describe()` and `findRun()` (alice owns `listRuns()`'s query,
    #558);
  - `src/Bridge/Temporal/Worker/`;
  - `src/DurableBundle/DependencyInjection/Loader/Observability.php`;
  - the plugin, `symfony/`, `src/DurableModule/`;
  - the conformance suite.
- **State**: taken over by anna from jane on 2026-09-28, at durable-30's request. Slices a and b
  merged in #564. Slice c is #567, merged with main at `5e98d8cb` and waiting on jack's re-check.
  Slice d is next, starting from jane's unpushed WIP `0e0d7c84` (`feat/temporal-records-the-wait`),
  and closes #514. Then comes the magento-boot check on a run id with a slash, prepared as a diff for
  durable-30. Reviewer: jack.
