# fix/in-memory-child-clock

- **Scope**: #652: a child run by the in-memory runner starts its virtual time at the parent's virtual now; delayed retries still fall due on the transport's clock.
- **Entries**: `src/Durable/` (ChildWorkflowRunner, InMemoryWorkflowRunner, VirtualClock), tests, `UPGRADE.md` if behaviour changes.
- **Overlap**: Same agent as fix/in-memory-timer-wins-backoff, done first; feat/execution-id-ports may touch the same files.
- **State**: in progress — subagent of durable-8a.
