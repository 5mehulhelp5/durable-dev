# feat/prises-check-release

- **Scope**: #376, second item. `bin/prises-check.sh --release` deletes the claim files it has just
  proven merged: closed PRs and a branch with nothing beyond `main`. A claim whose branch is gone
  stays reported, since nothing proves its work landed. CLAUDE.md names it as the one unattended
  write allowed under `.worktrees/prises/`.
- **Slices**: one, TDD — the flag, its cases in `bin/prises-check-test.sh`, the CLAUDE.md line.
- **Entries**: `bin/prises-check.sh`, `bin/prises-check-test.sh`, `CLAUDE.md`.
- **State**: taken — durable-2e.
