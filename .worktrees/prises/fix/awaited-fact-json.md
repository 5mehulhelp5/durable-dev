# fix/awaited-fact-json

- **Scope**: #627, as the user decided on it: `AwaitedFact` round-trips through Messenger's Symfony
  Serializer (JSON) via a public validating constructor; `symfony/serializer` in the root require-dev.
- **Entries**: `src/Durable/Transport/AwaitedFact.php`, root `composer.json`/`composer.lock`,
  `tests/unit/Durable/Transport/`, `UPGRADE.md`.
- **State**: in progress — jane. Reviewer: sirius.
