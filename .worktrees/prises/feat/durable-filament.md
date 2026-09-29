# feat/durable-filament

- **Scope**: #712: the new package gplanchat/durable-filament, a Filament 3 and 4 panel plugin over the RunDashboard projection (run list, run page with Nexus operations in flight), EN and FR. The user approved its composer.json and root manifest/lock entries on 2026-09-29; the SPLITS line waits for the user to create the satellite repo and extend the PAT.
- **Entries**: `src/DurableFilament/` (new), `tests/unit/DurableFilament/`, the laravel/ bench, root `composer.json`/`composer.lock` (approved), docs EN/FR.
- **Overlap**: durable-cd's #707/#708 (Nexus on Sylius and Magento dashboards) set the pattern; #703 (ExecutionId) touches the ports.
- **State**: in progress — subagent of durable-8a.
