# feat/loop-issue-trust-gate

- **Work item**: a trust gate on the loop's reading list. The repository is public and 12 of its 25
  open issues are authored by accounts with no association to it, yet the loop's triage receives no
  author information at all — a stranger's issue and the owner's arrive identical. A CI workflow
  labels every issue by `author_association` at submission time, and the loop reads only what that
  gate has cleared.
- **Entry points**: `.github/workflows/issue-trust-gate.yml` (new), `loop/loop.sh`,
  `loop/contract.md`, `loop/RUNBOOK.md`, `documentation/wa/WA007-…md`. Nothing under `src/`.
- **State**: in progress. Stacked on `feat/agentic-os` (PR #284), which brings `loop/`.
