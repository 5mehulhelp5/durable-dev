# fix/dependabot-magento-lock-replay

- **Work item**: the 40 Dependabot alerts (#27 to #67) on `magento/composer.lock` reopened after
  329a7695 (#302) carried the lock by accident and undid every bump of #394. Restore the lock as
  merged in #394 (0780f8c5). One commit, lock file only.
- **Inputs**: `magento/composer.lock`. No code, no test.
- **State**: in review.
