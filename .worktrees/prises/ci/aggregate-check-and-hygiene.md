# ci/aggregate-check-and-hygiene

- **Work item**: one `ci-ok` job that needs every other job, to become the single required
  check; workflow-level concurrency, a timeout on every job, a Composer cache on the jobs that
  install, and a Dependabot configuration. Issue #349, day 5 of the roadmap #304.
- **⚠ Supervised scope, authorised by a human**: `.github/workflows/ci.yml` and the branch
  protection. The human asked for #349 by number.
- **Inputs**: `.github/workflows/ci.yml`, `.github/dependabot.yml` (new).
- **State**: in progress.
