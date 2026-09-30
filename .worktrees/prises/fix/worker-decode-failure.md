# fix/worker-decode-failure

- **Scope**: #775: a payload that fails to decode fails the task (workflow and activity) instead of stopping the worker.
- **Entries**: src/Bridge/Temporal/Worker, src/Bridge/Temporal/Codec, their tests, UPGRADE if needed.
- **Overlap**: none known
- **Session**: durable-8a (subagent), 2026-09-30.
- **State**: in progress.
