# fix/magento-activity-handler-contract

- **Scope**: #715: #[AsActivityHandler(contract)] narrows what a Magento handler serves.
- **Entries**: `src/DurableModule/`, `tests/unit/DurableModule/`, `UPGRADE.md`.
- **Overlap**: spike/magento-sql-backend (#709) lives in spike/ only; #706/#708 touch the Magento admin, not RuntimeFactory.
- **State**: in progress — subagent of durable-8a.
