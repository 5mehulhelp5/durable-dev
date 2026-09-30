# fix/temporal-worker-keeps-polling

- **Scope**: #824 and #840: a decode failure on a later history page and an InvalidArgument on respond fail the task and the worker keeps polling.
- **Entries**: src/Bridge/Temporal/Worker (WorkflowTaskProcessor, TemporalHistoryCursor), tests.
- **State**: in progress.
