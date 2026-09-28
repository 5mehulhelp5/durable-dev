# chore/phpunit-memory-limit

- **Scope**: phpunit.xml caps memory_limit, so a runaway test fails instead of taking the IDE and every
  session down (four global OOMs on 2026-09-28, a php process at 36 GB).
- **Entries**: `phpunit.xml`, one guard test under `tests/unit/`.
- **Overlap**: none.
- **State**: in progress — durable-97.
