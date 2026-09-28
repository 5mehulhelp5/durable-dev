# docs/host-configuration-parity

- **Scope**: #357. A three-column host table (Symfony, Laravel, Magento) in
  `documentation/user/configuration/`, one proposal per key for the user to approve row by row;
  Laravel `max_activity_retries` configurable; Magento `budgetSeconds` documented.
- **Entries**: `documentation/user/configuration/` (EN + FR), `src/DurableLaravel/config/durable.php`,
  `src/DurableLaravel/DurableServiceProvider.php`, their tests.
- **Not in scope**: adding the keys a row marks "to add"; each gets its own task.
- **State**: taken — emma. Reviewers: jack (docs), sirius (code).
