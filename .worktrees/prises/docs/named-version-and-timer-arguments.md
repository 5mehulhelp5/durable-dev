# docs/named-version-and-timer-arguments

- **Work item**: follow-up to #698. Name `minSupported:`/`maxSupported:` of `version()`,
  `timerSummary:` of `sleep()`/`timer()`, `payload:`/`timeouts:` of `nexusOperation()` and `from:`
  of `Duration::until()` wherever they are passed positionally. User request, 2026-09-29.
- **Entry points**: `documentation/user/`, `UPGRADE.md`, the laravel/magento/symfony benches,
  `tests/`, one docblock in `src/Durable/WorkflowEnvironment.php`.
- **State**: PR #699 open.
