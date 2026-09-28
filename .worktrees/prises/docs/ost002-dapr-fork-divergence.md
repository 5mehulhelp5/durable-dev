# docs/ost002-dapr-fork-divergence

- **Work item**: update OST002 with the Dapr protocol fork. `dapr/durabletask-go` now builds on
  `dapr/durabletask-protobuf`, which renamed orchestrations to workflows and changed the action
  set, so "one bridge reaches three hosts" no longer holds.
- **Entry points**: `documentation/ost/OST002-durable-task-backend-feasibility.md`, and the
  matching summary line in `documentation/ost/OST001-alternative-durable-execution-backends.md` §3.
- **State**: in progress.
