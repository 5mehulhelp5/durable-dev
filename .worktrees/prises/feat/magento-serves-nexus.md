# feat/magento-serves-nexus

- **Scope**: #668 — Magento serves Nexus operations: handlers listed in di.xml (`nexusHandlers` on RuntimeFactory), their contract read from `#[AsNexusServiceHandler]`, fulfilling workflows from `workflowClasses` via `#[FulfilsNexusOperation]`, and `durable:worker --role=nexus`. The declaration logic moves from Laravel's DeclaredNexusOperations into the core, shared by both. No ADR (user's decision, 2026-09-29).
- **Entries**: `src/Durable/Nexus/Serving/`, `src/DurableLaravel/Nexus/`, `src/DurableModule/` (Runtime/RuntimeFactory.php, etc/di.xml, Console/Command/RunWorkerCommand.php, README), `tests/`, `openspec/specs/magento-host/spec.md`, `UPGRADE.md`.
- **Overlap**: feat/magento-worker-health also edits RuntimeFactory (workers()); rebase at merge.
- **State**: in progress — human-supervised session.
