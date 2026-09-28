# feat/run-catalog-previous-cursor

- **Scope**: #383, the reverse cursor the user decided on 2026-09-28. `WorkflowRunPage` gains
  `previousCursor` (null on the first page). The opaque cursor carries the stack of cursors that
  led to it, and "previous" replays the page before from its token; on Temporal, the same visibility
  query re-issued with the earlier page token. Same semantics on the four catalogues, capped at 50
  pages (then back to the first page), with a conformance case: previous of next is the page you
  started from. The Sylius page switches from its URL back stack to it once #609 is on main.
- **Entries**: `src/Durable/Observation/WorkflowRunPage.php`, a cursor-stack helper in
  `src/Durable/Observation/`, the four catalogues, `WorkflowRunCatalogConformanceTestCase`,
  `RunDashboard`, `UPGRADE.md`; later `src/DurablePlugin/Controller/` and its templates.
- **State**: in review — PR #615, alice. Reviewer: jack. Only durable-30 merges. The Sylius page's
  switch to it follows #609.
