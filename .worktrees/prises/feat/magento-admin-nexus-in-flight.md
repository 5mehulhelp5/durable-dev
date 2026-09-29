# feat/magento-admin-nexus-in-flight

- **Scope**: #672 — the Magento admin's run page (`durable-magento`, ProcessDetail) shows a run's Nexus operations: endpoint, service, operation, in flight until settled; magento-boot opens the page of a run held in flight.
- **Entries**: `src/DurableModule/Block/Adminhtml/ProcessDetail.php`, `src/DurableModule/view/adminhtml/templates/process/detail.phtml`, `tests/unit/DurableModule/`, `.github/workflows/ci.yml` (magento-boot).
- **Overlap**: builds on feat/plugin-nexus-in-flight (#671, the companion NexusOperationCatalogInterface); its PR opens after #700 and #671. Watch feat/magento-worker-health, which touches the admin banner.
- **State**: in progress — human-supervised session.
