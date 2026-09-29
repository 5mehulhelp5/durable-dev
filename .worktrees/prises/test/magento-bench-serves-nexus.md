# test/magento-bench-serves-nexus

- **Scope**: #669 — the Magento bench serves both response shapes from its probe module (a billing handler with `#[AsNexusServiceHandler]`, a workflow fulfilling charge), called by the demo harness in magento-boot through `durable:worker --role=nexus`.
- **Entries**: `magento/app/code/Gplanchat/DurableProbe/` (Nexus handler, workflow, di.xml), `.github/workflows/ci.yml` (magento-boot).
- **Overlap**: builds on feat/magento-serves-nexus (#720); draft PR until #720 is on main. feat/magento-admin-nexus-in-flight (#708) also adds a magento-boot step.
- **State**: in progress — human-supervised session.
