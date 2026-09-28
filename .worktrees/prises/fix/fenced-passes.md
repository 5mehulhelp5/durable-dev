# fix/fenced-passes

- **Scope**: #505, as DUR053 decides: `FencedEventStoreInterface` (capability), `PassFence`,
  `SupersededPassException`; InMemory, DBAL and Illuminate stores; the engine and handlers claim a
  fence per pass; conformance cases.
- **Entries**: `src/Durable/Store/`, `src/Durable/Exception/`, `src/Durable/Testing/`,
  `src/Durable/ExecutionEngine.php`, `src/Durable/ExecutionRuntime.php`, `src/Durable/Handler/`,
  `src/Durable/InMemoryWorkflowRunner.php`, `src/Bridge/Dbal/`, `src/Bridge/Illuminate/`, their tests.
- **State**: taken — elsa. Reviewer: sirius.
