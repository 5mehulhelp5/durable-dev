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
- **State**: anna, taken over from jane on 2026-09-28. Slices a and b merged in #564, c in #567, d in
  #570; #514 is closed. Left: the magento-boot check on a run id with a slash. The job has no DSN,
  so a run the admin can read needs Temporal there: waiting for the user to choose between (A)
  Temporal in the job, (B) a not-found page for a slashed id on the memory backend, or (C) B then A.
