# test/nexus-demo-harness

- **Scope**: #663 — a CI harness that serves the demo Nexus contracts (`stock`, `billing`, `delivery`) on a dev server, both response shapes, so the bench jobs of epic #673 have a counterpart to call.
- **Entries**: `tests/integration/Temporal/` (harness worker and its test), possibly a `bin/` launcher; no workflow change in this branch.
- **Overlap**: every other #673 sub-issue depends on this one.
- **State**: in progress — human-supervised session.
