# test/magento-bench-nexus-calls

- **Scope**: #667 — the Magento bench calls Nexus operations in CI: `magento-boot` starts the demo harness and runs `bin/magento durable:demo:nexus` against it, asserting the run completes and the call order from its history.
- **Entries**: `.github/workflows/ci.yml` (job `Magento · bench + dashboard`), possibly the bench's probe module under `magento/app/code/Gplanchat/DurableProbe/`.
- **Overlap**: none with fix/magento-runtime-logger (module di.xml); reuses the demo harness (#663).
- **State**: in progress — human-supervised session.
