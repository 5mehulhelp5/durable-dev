# test/laravel-bench-nexus

- **Scope**: #666 — the Laravel bench's Nexus probe becomes a round trip against a server: `delivery/schedule` (DeliveryHandler, declared in `config/durable.php`) and `delivery/ship` (ShipWorkflow, which calls `stock/reserve` while it serves), served by `php artisan durable:nexus-worker`, with the demo harness on the other side.
- **Entries**: `laravel/` (a test of its own), `.github/workflows/ci.yml` (job `Laravel · bench, Nexus probe`: a Temporal dev server and the root vendor).
- **Overlap**: reuses the harness's `--call` and `--bench` (#685).
- **State**: in progress — human-supervised session.
