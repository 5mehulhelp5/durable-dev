# ci/temporal-120-namespace-cache

- **Scope**: CI: the Temporal 1.20 job takes 18 min because every test waits for two 10 s namespace-cache refreshes of the 1.20 server; set `system.namespaceCacheRefreshInterval` to 1s through dynamic config.
- **Entries**: `.github/workflows/ci.yml` (job `temporal-oldest-server`).
- **Overlap**: ci/temporal-transport-matrix (#660) edits other jobs of the same file.
- **State**: in progress — human-supervised session.
