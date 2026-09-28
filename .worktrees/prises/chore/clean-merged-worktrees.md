# chore/clean-merged-worktrees

- **Scope**: #374, third item. `make clean-worktrees` removes every worktree whose branch is in
  `origin/main`, without `--force`, so a worktree holding local work is refused and reported. The
  existing removal of the loop's `loop-*` worktrees is left as it is.
- **Slices**: one, TDD — `bin/clean-worktrees.sh` and its test `bin/clean-worktrees-test.sh`, the
  Makefile target calling it.
- **Entries**: `Makefile`, `bin/`.
- **State**: taken — owner's session.
