# test/messages-json-round-trip

- **Scope**: #643: every Durable Messenger message survives the Symfony Serializer JSON round trip;
  one test per message class, a guard for uncovered classes, fixes the way #637 fixed `AwaitedFact`.
- **Entries**: `tests/unit/Durable/Transport/`, `src/Durable/Transport/`, the value objects those
  messages carry (`src/Durable/Activity/` for `ActivityOptions`), `UPGRADE.md`.
- **Overlap**: elsa's #616 may add a message; the PR says which lands first.
- **State**: in progress — jane. Reviewer: sirius.
