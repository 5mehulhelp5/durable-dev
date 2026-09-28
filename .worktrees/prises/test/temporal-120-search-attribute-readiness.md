# test/temporal-120-search-attribute-readiness

- **Scope**: #650: FreshNamespace waits until a start naming the Durable search attributes is accepted on Temporal 1.20, not only a visibility query.
- **Entries**: `tests/integration/Temporal/FreshNamespace.php` and the Temporal integration fixtures. The `.github/workflows/ci.yml` wait step is proposed in the PR body, not edited (supervised).
- **Overlap**: none open.
- **State**: in progress — subagent of durable-97.
