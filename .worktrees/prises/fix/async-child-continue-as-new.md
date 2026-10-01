# fix/async-child-continue-as-new

- **Scope**: #859: on the journal backends, the parent link follows an async child's continue-as-new chain and the parent hears of the last run.
- **Entries**: src/Durable (ResumeWorkflowHandler, ChildWorkflowRunner, parent link store), src/Bridge/Dbal and Illuminate stores if the link moves, tests.
- **State**: in progress.
