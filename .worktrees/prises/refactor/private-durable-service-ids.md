# refactor/private-durable-service-ids

- **Scope**: #342 C14: the `durable.*` service ids become private except those the docs name, before 0.1.0-beta1 (the user's decision, 2026-09-29), with Rector or UPGRADE for every removed public id.
- **Entries**: `src/DurableBundle/`, its container snapshot and surface tests, `src/DurableRector/` if a rule applies, `UPGRADE.md`.
- **Overlap**: docs/beta1-install-lines may touch the same user docs; this branch does not edit documentation/user.
- **State**: in progress — subagent of durable-97.
