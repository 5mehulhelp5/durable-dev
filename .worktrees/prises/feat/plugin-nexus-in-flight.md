# feat/plugin-nexus-in-flight

- **Scope**: #671 — the Sylius plugin's admin run page (`durable-plugin`) shows a run's Nexus operations: endpoint, service, operation, and in flight until settled; rendered for real in the Sylius job.
- **Entries**: `src/DurablePlugin/`, possibly `src/Durable/Observation/` and `src/Bridge/Temporal/Store/TemporalRunHistoryReader.php` (Nexus labels), `sylius/tests/Functional/`.
- **Overlap**: builds on feat/profiler-nexus-in-flight (#700, the shared NexusOperationSummary); its PR opens once #700 is on main.
- **State**: in progress — human-supervised session.
