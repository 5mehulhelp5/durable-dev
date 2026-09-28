# fix/temporal-timer-deadline

- **Scope**: #586. `TemporalEventConverter` builds `TimerScheduled` with the deadline (event time +
  `start_to_fire_timeout`), which is what `scheduledAt()` means on every other store.
- **Entries**: `src/Bridge/Temporal/Store/TemporalEventConverter.php`, its unit test.
- **State**: taken by bob, reviewer sirius.
