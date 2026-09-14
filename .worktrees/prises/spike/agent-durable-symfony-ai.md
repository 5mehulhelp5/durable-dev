# spike/agent-durable-symfony-ai

- **Scope**: a durable-agent prototype — make Symfony AI's `Runner::run()` replayable by driving it from workflow code, through two adapters (`ModelClientInterface`, `ToolExecutorInterface` — the low seam is not `PlatformInterface`, see §8 of 2026-08-31). Checks the four questions of §7 in `documentation/journal/inbox/2026-08-31.md`.
- **Entries**: `symfony/` (the sample application) only — no package, no bundle, no ADR.
- **State**: in review, PR #393 (opened 2026-09-12, `main` merged in the same day). The entry
  `documentation/user/use-cases/durable-agent.md` is back on the branch with a launch path on
  `http://localhost:8012`; the `_index` row returns at merge time. `symfony/ai` pinned to v0.13.0 —
  which is the latest published version, not an old pin. The reason is that `symfony/ai` is 0.x,
  with no compatibility promise between minors: the seams used (`ModelClientInterface`,
  `ResultConverterInterface`, `ToolExecutorInterface`, `ToolboxInterface`) are all public, and
  `Runner` — which is `@internal` — is never touched.
