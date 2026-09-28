# feat/sylius-grid-runs-list

- **Scope**: #383, slice B. The Sylius plugin lists runs through a `sylius/grid-bundle` grid whose
  data provider reads `WorkflowRunCatalogInterface`, paged by the catalogue's cursor (the user's
  decision: no Pagerfanta). Its filter controls use `canFilterRuns()` and `WorkflowRunFilter`.
- **Entries**: `src/DurablePlugin/` (the provider, grid config, controller, templates, tests),
  `sylius/grid-bundle` in `src/DurablePlugin/composer.json` and a regenerated `sylius/composer.lock`
  (both confirmed by the user), the provider excluded from the root PHPStan/Psalm paths (confirmed by
  the user; the Sylius bench CI covers it).
- **Not in scope**: DUR049's successor note on cursor vs Pagerfanta (a human decision record).
- **State**: taken — alice (bob released it). Reviewer: jack. Only durable-30 merges.
