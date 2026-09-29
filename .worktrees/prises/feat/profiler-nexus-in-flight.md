# feat/profiler-nexus-in-flight

- **Scope**: #670 — the Symfony debug toolbar panel (`DurableDataCollector`, durable-bundle) shows a run's Nexus operations with endpoint, service, operation and state: in flight until settled, then completed, failed, timed out or cancelled.
- **Entries**: `src/DurableBundle/DataCollector/`, the panel's Twig template, `tests/unit/DurableBundle/`.
- **Overlap**: none open; #671 and #672 are the Sylius and Magento dashboards of the same epic (#673).
- **State**: in progress — human-supervised session.
