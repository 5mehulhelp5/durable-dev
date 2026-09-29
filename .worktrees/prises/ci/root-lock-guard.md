# ci/root-lock-guard

- **Scope**: #640: the root composer.lock matches the path packages' manifests, and a CI check fails when it drifts. The lock refresh and the workflow change are supervised: the user approves.
- **Entries**: `composer.lock` (proposal), a script under `bin/`, a proposed `.github/workflows/` diff.
- **Overlap**: none open.
- **State**: in progress — subagent of durable-8a.
