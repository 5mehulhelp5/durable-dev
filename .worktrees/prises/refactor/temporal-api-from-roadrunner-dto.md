# refactor/temporal-api-from-roadrunner-dto

- **Scope**: #352, the user's decision of 2026-09-28. The bridge depends on
  `roadrunner-php/roadrunner-api-dto` ^1.17 for `Temporal\Api\…` instead of generating it. Step 0
  (posted on #352) found every class the code uses in v1.17.0, which is regenerated from Temporal
  API v1.63.5. `src/Bridge/Temporal/Api/`, `Generated/` and `.temporal-api-version` go, along with
  the two autoload mappings in the bridge's and the root's manifests.
- **Approved by the user in alice's session**: the root `composer.json` and lock, and a scoped
  refresh of the bench locks. Mechanical commits (the deletion, the locks) may exceed 200 lines;
  logic commits stay at 200 or under. `CLAUDE.md` stays untouched.
- **Entries**: `src/Bridge/Temporal/composer.json`, `composer.json`, the five `composer.lock`,
  `phpstan.neon`, `psalm.xml`, `phpunit.xml`, `.php-cs-fixer.dist.php`, the route test, the replay's
  event lists, `UPGRADE.md`.
- **State**: taken — alice. Reviewer: sirius. Only durable-30 merges.
