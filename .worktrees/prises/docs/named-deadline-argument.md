# docs/named-deadline-argument

- **Work item**: every `await()` call in `documentation/user/` and on the site passes its deadline
  as `deadline: ...` rather than positionally. User request, 2026-09-29.
- **Entry points**: `documentation/user/{workflows,concepts,cancellation}/`, `hugo-docs/layouts/index{,.fr}.html`,
  `hugo-docs/variant-b-narrative*.dc.html`. Not ADRs, not OpenSpec archives.
- **State**: PR #698 open.
