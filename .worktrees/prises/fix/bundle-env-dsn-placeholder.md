# fix/bundle-env-dsn-placeholder

- **Scope**: `durable.temporal.dsn: '%env(X)%'` without an `env(X)` default fails every Symfony container compile since #334: ValidateEnvPlaceholdersPass checks the placeholder with an empty string, and the blank-DSN rule refuses it. Accept placeholders, keep refusing a literal empty or blank DSN and a non-string one, keep an explicit null meaning "no cluster".
- **Entries**: `src/DurableBundle/DependencyInjection/Configuration.php`, `tests/unit/DurableBundle/DependencyInjection/`, `UPGRADE.md` if a note is due.
- **Overlap**: blocks test/sylius-bench-nexus (#665), whose `demo` and `demo_caller` profiles hit it.
- **State**: in progress — human-supervised session.
