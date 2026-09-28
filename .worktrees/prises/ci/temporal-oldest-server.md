# ci/temporal-oldest-server

- **Scope**: #523, the user's decision of 2026-09-28: Temporal 1.20 is the oldest supported server.
  - **Docs**: the backends documentation (EN + FR) states the oldest supported server. This
    goes in its own PR, `docs/oldest-temporal-server`, reviewed by jack.
  - **CI**: a job runs the run-catalogue and visibility-query integration suites against
    `temporalio/auto-setup:1.20` with Postgres. Its branch is `ci/temporal-oldest-server`, prepared
    locally; durable-30 pushes it over SSH. Reviewer: sirius.
- **Entries**:
  - `documentation/user/backends/_index.md`, `_index.fr.md`;
  - `.github/workflows/ci.yml`, prepared only;
  - no test edits unless 1.20 shows a real incompatibility.
- **State**: in progress — jane. #331's implementation waits for the user's approval of DUR051
  (#585).
