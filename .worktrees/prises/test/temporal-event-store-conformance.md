# test/temporal-event-store-conformance

- **Scope**: #326, remainder after #645: a TemporalEventStoreConformanceTest runs the event store conformance tiers on TemporalReadThroughEventStore against a real server.
- **Entries**: `tests/integration/Temporal/`, `src/Durable/Testing/` (docblocks, shared cases), `src/Bridge/Temporal/Store/` if a gap needs a fix.
- **Overlap**: #645 (test/temporal-replay-conformance) lands first; this branch starts from a main that contains it.
- **State**: in progress — subagent of durable-97.
