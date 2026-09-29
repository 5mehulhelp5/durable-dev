# chore/splitsh-self-version-rewrite

- **Scope**: #347 (b): at tag time, bin/splitsh-publish.sh rewrites each satellite's `self.version` requirements to the tagged version's caret range, so one composer require installs the beta line.
- **Entries**: `bin/splitsh-publish.sh` (supervised; the user approved this change on 2026-09-29), its tests if any, `UPGRADE.md`.
- **Overlap**: none open.
- **State**: in progress — subagent of durable-97.
