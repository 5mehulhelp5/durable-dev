# refactor/magento-read-through-store

- **Scope**: #356 part 2 and #372's last item. Magento's `RuntimeFactory` reads through
  `TemporalReadThroughEventStore` (one gRPC client per request is already in place);
  `TemporalJournalEventStore` and `HistoryPageMerger` are deleted with an UPGRADE entry
  (`JournalWorkflowTaskProcessor` already went in #517). Magento stays memory + Temporal.
  `TemporalConnection::journalWorkflowId()` is left alone (alice's #566 leaves it out too).
- **Entries**: `src/DurableModule/Runtime/RuntimeFactory.php`,
  `src/Bridge/Temporal/TemporalJournalEventStore.php`, `src/Bridge/Temporal/Journal/HistoryPageMerger.php`,
  their docblock mentions and README row, `tests/unit/DurableModule/RuntimeFactoryTest.php`,
  `tests/integration/Temporal/TemporalJournalEventStoreConformanceTest.php`, `UPGRADE.md`.
- **State**: taken by bob, reviewer sirius.
