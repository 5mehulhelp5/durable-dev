# chore/release-tooling

- **Scope**: #350, taken over from durable-2e (the user's decision of 2026-09-28).
  - `bin/release.sh`: tag, then `gh release create --notes-start-tag <previous> --prerelease`. It
    refuses a tag not on `origin/main`, and it is documented in a WA.
  - `bin/splitsh-publish.sh` archives under `refs/archive/`. The file is supervised and the change
    is approved; it is prepared here and durable-30 pushes it.
  - The two archive branches on the satellites are deleted only once their SHAs are proven
    reachable from a tag. The PR lists each SHA with its tag, and durable-30 deletes or confirms.
  - The UPGRADE sections for alpha9 and alpha10, and the `RunDashboardView` Rector rename.
- **Entries**: `bin/release.sh`, `bin/splitsh-publish.sh` (prepared only), `documentation/wa/`,
  `UPGRADE.md`, `src/DurableRector/`.
- **State**: in progress — jane. Reviewers: sirius (code), jack (docs).
