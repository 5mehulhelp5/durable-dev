# feat/sylius-grid-runs-list

- **Scope**: #383, slice B, merged in #609. Its follow-up, per the user's decision on #383
  (2026-09-28): the previous-page feature is abandoned, since Temporal cannot page backwards. The
  Sylius run list pages forward only, with a link back to the first page; the URL back stack from
  #555 goes, and an old link carrying `?back=` lands on the first page, not a 404.
- **Entries**: `src/DurablePlugin/Controller/AdminDashboardController.php`, the list templates, the
  plugin's tests and translations, the Sylius bench's functional test, `UPGRADE.md`, the dashboard
  docs if they name the previous link.
- **State**: follow-up taken — alice (branch `fix/sylius-run-list-forward-only`). Reviewer: jack.
  Only durable-30 merges.
