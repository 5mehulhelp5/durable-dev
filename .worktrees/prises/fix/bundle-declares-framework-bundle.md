# fix/bundle-declares-framework-bundle

- **Scope**: #343 (B-10). `symfony/framework-bundle` in `src/DurableBundle/composer.json` `require`,
  `symfony/twig-bundle` in its `suggest` (the user's assignment: those two entries only), the
  README's Symfony line.
- **Entries**: `src/DurableBundle/composer.json`, `src/DurableBundle/README.md`,
  `tests/unit/DurableBundle/DependencyInjection/DurableDeclaredWiringTest.php`.
- **State**: in review, PR #592 — elsa. Reviewer: sirius.
