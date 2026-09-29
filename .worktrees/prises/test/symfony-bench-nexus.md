# test/symfony-bench-nexus

- **Scope**: #664 — the Symfony bench calls, serves now and serves through a workflow against a server: `billing/verify`, `billing/charge` (ChargeWorkflow) and ReserveStockWorkflow calling `stock/reserve`, with the demo harness (#663) on the other side. Starts by giving the harness a "call one operation" mode.
- **Entries**: `symfony/tests/Integration/Temporal/`, `tests/integration/Temporal/nexus-demo-harness.php` and its fixture; `.github/workflows/ci.yml` only if the `Temporal · ext-grpc, bench, TLS` job needs a step.
- **Overlap**: #665, #666, #669 will reuse the harness's call mode.
- **State**: in progress — human-supervised session.
