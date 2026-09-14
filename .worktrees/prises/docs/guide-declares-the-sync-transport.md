# docs/guide-declares-the-sync-transport

- **Work item**: the getting-started guide routes two messages to a `sync` transport its own
  `transports:` block never declares, so the container it teaches refuses to build. Issue #363,
  day 2 of the roadmap #304.
- **Inputs**: `documentation/user/getting-started/_index.md` and `_index.fr.md`, the Messenger
  block only. The CI job that follows the guide verbatim is the second half of #363 and not this
  slice.
- **State**: in progress.
