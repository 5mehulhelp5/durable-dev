# DUR056 — Magento journals through its own DB layer on a dedicated connection, with no Nexus

## Status

Proposed. `documentation/adr/` is supervised: this ADR takes effect when the user approves its
text on its pull request.

The user chose option B of spike #709 on 2026-09-30: Magento's own database layer, not Doctrine
DBAL. The measurements below come from that spike, draft PR #723. The same day the user ruled on
decision 3: a `resource/durable` that names the shop's `default` connection is accepted with a
warning, as in DUR054 decision 6.

Related:
- [DUR046](DUR046-magento-a-tier-1-host-that-improved-the-core.md), the Magento host. This ADR
  supersedes part of it (listed under Decision) and does not edit it.
- [DUR054](DUR054-the-journal-does-not-share-the-applications-connection.md): the journal does not
  share the application's connection. Decision 3 below applies it to Magento.
- [DUR051](DUR051-a-backend-refuses-what-it-cannot-honour.md): a backend refuses by name what it
  cannot honour. That is how Nexus is refused here.
- [DUR053](DUR053-a-superseded-pass-cannot-write.md): the fencing epoch, which the new event store
  implements.

## Context

DUR046 gave Magento two backends, `memory` and `temporal`, "final rather than provisional", because
`ResourceConnection` is neither Doctrine DBAL nor Illuminate's connection, and it says "Magento has
no native journal and will not get one". On 2026-09-29 the user reopened the question: Magento
should run Durable on a database, like Symfony and Laravel, on a connection of its own, with no
Nexus.

Spike #709 compared two ways to get that connection:

- **A:** a Doctrine DBAL connection built from its own `env.php` entry, `durable/db/url`, with the
  `durable-bridge-dbal` stores unchanged.
- **B:** a connection declared through Magento (`db/connection/durable` plus `resource/durable`),
  read through `Magento\Framework\App\ResourceConnection`, with a new family of stores over
  `Magento\Framework\DB\Adapter\AdapterInterface` (`Pdo\Mysql`).

What the spike measured through Magento's adapter, on Mage-OS 2.2.0 and MySQL 8.4
(`probe-b.php`):

- An inner `rollBack()` followed by the outer `commit()` throws `Rolled back transaction has not
  been completed correctly`.
- A `createTable()` inside a transaction is accepted, and MySQL commits the transaction implicitly.
- `getConnection('durable')` silently returns the **shop's** connection (same `CONNECTION_ID()`,
  database `magento`) unless `resource/durable` is also declared. `getConnectionByName('durable')`
  reached the journal's server.
- `db_schema.xml` with `resource="durable"`: without `resource/durable` in `env.php`,
  `setup:upgrade` exits 0 and creates the table in the shop's database; with it, developer mode
  rejects the file, because `resource` is an XSD enumeration (`default`, `checkout`, `sales`) that a
  module cannot extend.
- The DUR053 fence's SQL works through the adapter: a pass claim's `UPDATE` held the heads row, and
  a `LOCK IN SHARE MODE` read from another process waited 2.53 s for it.

The stores themselves were not built under B. By extrapolation from the DBAL stores and schema
(1007 lines) and Illuminate's (825), they are about 1000 lines of new code.

## Decision

1. **Magento may journal to SQL, through its own DB layer.** The stores are written over
   `AdapterInterface`, obtained from `ResourceConnection`. Doctrine DBAL and Illuminate stay out of
   the module, and the SQL path adds no Composer package.
2. **The connection is a declared resource.** The backend reads `resource/durable` from `env.php`
   through `DeploymentConfig` and resolves the connection it names with `getConnectionByName()`.
   A dedicated `db/connection/durable` is the recommended target.
3. **The silent fallback is refused by name.** If `resource/durable` is not declared, or names a
   connection absent from `db/connection`, the backend refuses to boot with an exception that names
   the missing key. It never calls `getConnection('durable')` and accepts what comes back. A
   `resource/durable` that names `default` is accepted and logs a warning that names it, as DUR054
   decision 6 does on Symfony and Laravel. On `default`, the journal's adapter is the shop's: a
   workflow started inside a shop transaction (an observer during checkout) meets the nesting
   constraint below and is refused.
4. **`resource/durable` and `durable/temporal/dsn` both set is refused by name** at boot.
5. **No Nexus.** Registering a Nexus handler and calling a Nexus operation are refused by name
   (`NexusUnsupportedByBackendException`), as on the other journal backends (DUR051).
6. **Resumes, timers and activities go through a leased table on the journal's connection.**
   Magento's MessageQueue stays out. Timers travel as `FireWorkflowTimersMessage`: the spike first
   sent them as delayed plain resumes, and spun 1146 passes on a timer that never fired.
7. **The locks are built on the journal's connection too**: the per-execution resume lock and the
   activity attempt claim, as a TTL row or as `GET_LOCK` on that connection, chosen by measurement
   (#732). Magento's `LockManagerInterface` is not used: DUR046's objection was to its database
   backend, `GET_LOCK` on the **shop's** connection, which answers `true` without locking when the
   database is unavailable.

### The adapter's pitfalls are design constraints

The measured flaws above do not reject B. The implementation must handle each one, with a test:

- **An inner rollback throws.** A store never nests transactions. Each unit of work (a pass claim,
  a fenced append, a queue take) opens exactly one transaction and refuses to start one when
  `getTransactionLevel()` is not 0. A failure rolls back the whole unit.
- **DDL inside a transaction is committed implicitly.** No store issues DDL. Tables are created only
  by the setup command below, which refuses to run when a transaction is open.
- **`getConnection('durable')` falls back to the shop's connection.** Handled by decision 3. A test
  boots with `db/connection/durable` and no `resource/durable` and expects the refusal by name.
- **`db_schema.xml` cannot target the journal.** The module declares no Durable table in
  `db_schema.xml`.

### How the schema is created

**`bin/magento durable:setup`**, through the adapter's DDL API (`newTable()`, `createTable()`,
`isTableExists()`, `tableColumnExists()`, `addColumn()`), on the connection of decision 2, outside
any transaction. It is idempotent and additive: it creates what is missing and adds a column a
later version needs. At runtime a store that finds a table missing refuses by name and points to
`durable:setup`; it does not create it at its first write.

Why not the alternatives:

- **Creating tables at the first write**, as `DurableSchema::ensure()` does on DBAL: the first
  write is a store's own unit of work (a pass claim, a fenced append, a queue take), which runs in
  a transaction, and MySQL would commit that transaction implicitly.
- **A Setup patch:** Magento records an applied patch in `patch_list`, in the shop's database. A
  journal re-pointed to an empty database would never get its tables again, and a patch that runs
  while `resource/durable` is missing writes to the shop's database, as `db_schema.xml` does.
- **`db_schema.xml`:** the XSD measurement above.

### What this supersedes in DUR046, and only this

- "Magento has no native journal and will not get one";
- "Magento reaches `memory` and `temporal`, and this is final rather than provisional".

The `conflict` on `gplanchat/durable-bridge-dbal` and `gplanchat/durable-bridge-illuminate` stays:
Magento uses neither. What DUR046 measured still stands and decides 6 and 7 here: MySQL-backed
MessageQueue ran one message twice during a success and acknowledged undispatched messages after a
crash, and `LockManagerInterface`'s database backend works on the shop's connection.

## Rejected alternative: option A, Doctrine DBAL

Option A built a DBAL connection from `env.php` `durable/db/url` and reused the
`durable-bridge-dbal` stores unchanged. The spike ran it with the journal on a second MySQL server:
a workflow with an activity, an 8 s timer and a signal completed through two `kill -9`s of the
worker and a signal sent with no worker running; the DBAL conformance classes passed there (60
tests); it added 5 packages (doctrine/dbal, doctrine/deprecations, symfony/lock, symfony/messenger,
symfony/clock), which resolve on every Mage-OS line the module admits. The spike recommended it.

It is rejected by the user's decision: Magento manages its database through its own layer, and the
journal follows it. Those results stay in PR #723. None of them was measured under B.

## Consequences

- A new family of stores, about 1000 lines, over `AdapterInterface`: the event store with the
  DUR053 fence, metadata, the run catalogue and its projection, parent links, the attempt claim,
  the table queue. Each is split into its own ticket under epic #740.
- The shared conformance cases in `src/Durable/Testing` must run against these stores inside a
  Magento bootstrap. `composer test` does not load Magento, so only the Magento bench and CI's
  Magento jobs exercise them.
- What A proved for DBAL has to be proved again for B: the restart experiment, the conformance
  suites, the Nexus refusal.
- The application API does not change with the backend. The user set the rule on 2026-09-30: an
  application uses the same API whatever the backend and the host, and the only accepted exception
  is a functional limit of Nexus. The Magento SQL backend exposes the same application API as the
  other backends, and "no Nexus" (decision 5) is that one exception. The same day, the user also
  accepted that Magento cannot resolve an attribute on a constructor parameter: that is a limit of
  the host's object manager, not of this backend, and it changes nothing this ADR decides. The
  parity audit of the same day found two gaps on Magento: no dispatcher, and no signal delivery on
  the application side. This ADR does not close them. The OpenSpec change proposed in PR #782, one
  client API on every backend and host through a repository per workflow, does.
- The public promise changes: the picker, the backends and configuration pages (EN and FR), the
  module README, and an UPGRADE entry. That is done last, in #739, not in this ADR's pull request.
