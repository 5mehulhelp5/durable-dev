# ci/install-from-packagist

- **Scope**: #347 (a), first item. `bin/guide-follows.sh --packagist` installs a fresh Symfony
  skeleton from Packagist by running the getting-started install block verbatim, and a weekly
  `install-from-packagist` workflow runs it. The workflow is handed to durable-30 to push.
- **Entries**: `bin/guide-follows.sh`, `.github/workflows/install-from-packagist.yml`.
- **State**: taken — durable-2e. Reviewer: sirius.
