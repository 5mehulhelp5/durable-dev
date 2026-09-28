# fix/temporal-prefix-filter-server-version

- **Scope**: the user's decision on #523. Temporal servers before 1.23.0 reject `STARTS_WITH`
  (jane measured 1.20 and 1.22 rejecting it, 1.23.0 accepting it). The Temporal catalogue reads the
  server version (`GetSystemInfo`) and declares the execution-id prefix filter unavailable below
  1.23.0, while the workflow-name filter keeps working. The capability asks about a given filter:
  `canFilterRuns(?WorkflowRunFilter $filter = null)`.
- **Entries**: `WorkflowServiceClientInterface` and `WorkflowRpcMethods` (`GetSystemInfo`), the JSON
  gateway routes, `WorkflowRunCatalogInterface::canFilterRuns()` and the four catalogues,
  `RunDashboard`, the conformance suite, `UPGRADE.md`. jane carries the docs naming 1.23 (#608).
- **State**: in review — PR #629, alice. Reviewer: sirius. Only durable-30 merges.
