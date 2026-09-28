# fix/receive-only-temporal-transports

- **Scope**: #353, slice C (M21). A `ReceiveOnlyTransport` trait replaces the three copies of the
  `send()`/`ack()`/`reject()` stubs on the Temporal receivers; `messenger:consume` warns when it is
  given an option these receivers never honour (`--limit`, `--failure-limit`), naming the ones that
  work; the bridge README says what applies to them and what does not.
- **Entries**: `src/Bridge/Temporal/Messenger/`, `src/Bridge/Temporal/README.md`, a console listener
  under `src/DurableBundle/`, their tests, `psr/log` in `src/DurableBundle/composer.json` (approved
  by the user, that package in that manifest only).
- **Not in scope**: the TLS Temporal in the integration job (a `.github/workflows/` change, the user's).
- **State**: in review — PR #602, alice. Reviewer: jack (approved at 18c43f58). Only durable-30
  merges.
