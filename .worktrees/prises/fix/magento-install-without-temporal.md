# fix/magento-install-without-temporal

- **Scope**: #725: durable-magento installs without durable-bridge-temporal (RuntimeFactory must not reflect bridge types).
- **Entries**: `src/DurableModule/`, `tests/unit/DurableModule/`, a proposed CI diff.
- **Overlap**: fix/magento-activity-handler-contract (#719) touches RuntimeFactory too; merge main often.
- **State**: in progress — subagent of durable-8a.
