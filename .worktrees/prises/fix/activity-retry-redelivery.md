# fix/activity-retry-redelivery

- **Scope**: #590, on Temporal's model (the user, 2026-09-28): an `ActivityRetryQueued` fact records the
  queued retry (Temporal's dispatch task), a redelivered failure without it queues it again, and a start
  claim per (execution, activity, attempt) drops a copy delivered while another runs it. Core port
  with a no-op default; symfony/lock implementation in the DBAL bridge; LockProvider one for Laravel.
- **Entries**: `src/Durable/Worker/ActivityMessageProcessor.php` (retry branch and guards only; anna's
  #328 slice owns the terminal paths), `src/Durable/Store/ActivityEventJournal.php`, a new port under
  `src/Durable/`, `src/Bridge/Dbal/`, `src/Bridge/Illuminate/`, the bundle and Laravel wiring, tests,
  `UPGRADE.md`.
- **State**: in review — PR #610, bob. Reviewer: sirius. Main merged in; anna's #328 slice merges main in if it lands second.