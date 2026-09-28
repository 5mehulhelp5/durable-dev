# docs/install-alpha-stability

- **Scope**: #347 (a). Every install block of the user docs (getting-started, packages; EN + FR)
  carries `composer config minimum-stability alpha` and `composer config prefer-stable true`,
  checked by `bin/guide-follows.sh`. The weekly install-from-Packagist job follows in a second PR.
- **Entries**: `documentation/user/getting-started/`, `documentation/user/packages/`,
  `bin/guide-follows.sh`.
- **State**: taken — durable-2e. Reviewers: jack (docs), sirius (check).
