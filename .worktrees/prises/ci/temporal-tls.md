# ci/temporal-tls

- **Scope**: #353, last item: the Temporal integration job also runs the Symfony bench's Temporal tests
  through a TLS DSN (`temporal+tls://…&ca=&cert=&key=&api_key=`). Throwaway CA and certificates
  generated in the job; `start-dev` has no server-side TLS, so an nginx gRPC terminator in front of
  it verifies the client certificate and the API key.
- **Entries**: `.github/workflows/ci.yml` (the `temporal-integration` job only).
- **State**: taken by bob, prepared on a local branch (the user approved the workflow edit, local only);
  durable-30 reviews and pushes it over SSH. Reviewer: sirius.
