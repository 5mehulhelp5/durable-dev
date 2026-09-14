# chore/ignore-the-checkout

- **Work item**: `.gitignore` covers what lives in the checkout without belonging to the
  repository — `.idea/`, `.claude/`, `.agents/`, `skills-lock.json`, and the per-task checkouts
  under `.worktrees/` (the registry `prises/` and `PRISES.md` stay tracked). Issue #374, day 1 of
  the roadmap #304.
- **Inputs**: `.gitignore` only. No worktree is removed by this slice.
- **State**: in progress.
