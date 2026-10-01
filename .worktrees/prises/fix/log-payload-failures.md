# fix/log-payload-failures

- **Scope**: #936: the worker logs a payload failure (stack trace, event id) before answering the task as failed, on the #925 and #807/#824 paths; corrects the #925 UPGRADE wording.
- **Entries**: src/Bridge/Temporal/Worker, src/Bridge/Temporal/Codec, tests, UPGRADE.
- **Overlap**: none known
- **Session**: durable-8a (subagent), 2026-10-01.
- **State**: in progress.
