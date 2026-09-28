# spike/restate-cancel-signals

- **Work item**: #641, the second Restate spike. Native cancellation, retries off and crash,
  journal size, named signals, against `restate-server` 1.7.12 in Docker.
- **Entry points**: `spike/restate/` only. Nothing under `src/`, no dependency.
- **Careful**: `run.sh` gets configurable container name and ports first; the first spike's fixed
  name and `pkill` pattern could hit another session's instance.
- **State**: in progress.
