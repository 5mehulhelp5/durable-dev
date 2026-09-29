# test/sylius-bench-nexus

- **Scope**: #665 — the Sylius bench serves `stock/reserve` (StockHandler, declared by a tag under `when@demo`) and calls `billing` through `NexusPayments`, against a server with Nexus on, the demo harness on the other side.
- **Entries**: `sylius/tests/`, `.github/workflows/ci.yml` (job `Sylius · dashboard, rendered`: a Temporal dev server and the root vendor).
- **Overlap**: reuses the harness's `--call` and `--bench` from #685.
- **State**: in progress — human-supervised session.
