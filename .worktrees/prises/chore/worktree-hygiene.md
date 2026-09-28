# chore/worktree-hygiene

- **Scope**: #374, taken over from durable-2e (the user, 2026-09-28).
  - `make clean-worktrees` removes every worktree whose branch is in `origin/main`, without
    `--force`. This landed in #571.
  - For the local `review/*` branches left by other sessions: ask their owners before any deletion.
  - For each remaining worktree whose branch is not in `main`: a decision, proposed to the user
    through durable-30. No `--force` on anything that is not mine.
  - `git status --short` on a fresh session shows nothing.
- **Entries**: `Makefile` (if needed), `.gitignore` (if needed), local git state only; no source code.
- **State**: in progress — jane. Reviewers: sirius (code), jack (docs).
