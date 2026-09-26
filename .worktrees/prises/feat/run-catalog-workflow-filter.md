# feat/run-catalog-workflow-filter

- **Scope**: #558 and #557. When `durable.temporal.search_attributes` is on (off by default), Durable
  writes two Keyword search attributes at every Temporal start: `DurableWorkflowName` and
  `DurableExecutionId`, both spelled by `DurableSearchAttributes::value()`, which normalizes the
  value and hashes it past 255 characters. `listRuns()` filters by workflow name and by execution-id
  prefix on all four catalogues, and `canFilterRuns()` says whether a catalogue can. Step 0
  (Server 1.25.2 + Postgres) is on #558. `findRun()` and the run identity on Temporal are jane's
  (#514).
- **Slices**:
  - S1: merged. PR #562 (the writer) went in on a stale head. PR #568 carried the user's two
    decisions (opt-in, hash past 255). CI registration was #563 (applied by durable-30).
  - S2, PR #569 (branch `feat/run-catalog-filters`): the `WorkflowRunFilter` VO, the filters on
    every catalogue (MySQL/MariaDB compared as BINARY), `canFilterRuns()` plus
    `RunFilterUnavailableException`, conformance on SQLite, MySQL 8.4, PostgreSQL 16 and
    Temporal 1.25.2. Folds in jane's 1c60c18a.
- **Entries**: `src/Durable/Observation/WorkflowRunFilter.php`, `src/Durable/Port/WorkflowRunCatalogInterface.php`,
  the four catalogues, `src/Durable/Exception/RunFilterUnavailableException.php`,
  `src/Durable/Testing/WorkflowRunCatalogConformanceTestCase.php`, the Temporal conformance double,
  the three test doubles, `UPGRADE.md`.
- **State**: S2 in review — PR #569, alice. Reviewer: sirius. Only durable-30 merges.
