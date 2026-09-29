# ci/temporal-transport-matrix

- **Scope**: CI: merge `no-grpc` and `grpc-integration` into one transport matrix (grpc-curl without ext-grpc, Guzzle), drop the ext-grpc run that duplicates `temporal-integration`, and run `--testsuite temporal` instead of `integration,temporal` in the Temporal jobs.
- **Entries**: `.github/workflows/ci.yml`.
- **Overlap**: none open; the Temporal 1.20 job's duration is a follow-up, not this branch.
- **State**: in progress — human-supervised session.
