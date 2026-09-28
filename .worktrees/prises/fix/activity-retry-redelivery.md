# fix/activity-retry-redelivery

- **Scope**: #590, on Temporal's model (the user, 2026-09-28): a redelivered attempt whose failure
  will retry re-sends the next attempt until it shows in the journal (at-least-once), and a start
  claim per (execution, activity, attempt) drops a copy delivered while another runs it. Core port
  with a no-op default; symfony/lock implementation in the DBAL bridge; LockProvider one for Laravel.
- **Entries**: `src/Durable/Worker/ActivityMessageProcessor.php` (retry branch and guards only; anna's
  #328 slice owns the terminal paths), `src/Durable/Store/ActivityEventJournal.php`, a new port under
  `src/Durable/`, `src/Bridge/Dbal/`, `src/Bridge/Illuminate/`, the bundle and Laravel wiring, tests,
  `UPGRADE.md`.
- **State**: taken by bob, reviewer sirius. Lands after or before anna's #328 slice; the second merges main in.
