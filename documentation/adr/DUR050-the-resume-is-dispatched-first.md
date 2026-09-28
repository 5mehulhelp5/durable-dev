# DUR050 — The resume is dispatched first, and a resume that arrives early waits

## Status

Proposed — drafted by an agent for #328. `documentation/adr/` is supervised: this ADR takes effect
when the user approves it on its pull request. The direction (option b below) is the user's
decision of 2026-09-28; the three choices under "Open for approval" are not decided yet.

Related to [DUR030](DUR030-dbal-backend-simplified-durable-execution.md), whose single-database
backend is the one the gap below bites hardest; DUR030 is not amended.

## Context

When an activity worker finishes an attempt, two things happen, in this order: the outcome is
appended to the journal, then a `ResumeWorkflowMessage` is sent so that a workflow worker replays
the execution and moves on (`ActivityMessageProcessor`). Nothing makes the two atomic, and no
sweeper looks for an execution whose last activity settled without a resume.

A crash between the two leaves exactly that: an execution that is complete in its journal and that
nothing will ever advance. The Messenger dispatcher makes it wider than it looks. Every resume
carries `DispatchAfterCurrentBusStamp`, so a resume sent from inside the activity handler is held in
memory until the handler returns. On Symfony the call order in the processor is therefore
irrelevant today: the send always happens after the append, and a crash anywhere after the append
loses it.

The same "append, then send" pair appears elsewhere: signal and update delivery
(`DeliverWorkflowSignalHandler`, `DeliverWorkflowUpdateHandler`), a child's outcome reported to its
parent (`ResumeWorkflowHandler::finalizeAsyncChildOnParentIfLinked()`), and timer wake-ups
(`FireWorkflowTimersHandler`).

Three options were considered in #328:

- (a) when the Messenger transport is Doctrine on the journal's connection, append and send in one
  transaction. Only one transport and one backend qualify, and the others keep the gap.
- (b) send the resume first, and make the resume safe to receive before the outcome it announces.
- (c) a real outbox table and a sweeper. It closes the gap everywhere, at the cost of a table, a
  poller, and a component to operate.

`durable.activity_transport.table_name` (default `durable_activity_outbox`) names an outbox that
was never built.

## Decision

**Option (b)** (the user's decision): the activity worker sends the resume **before** it appends
the outcome, and the resume contract becomes **at-least-once**.

1. **A resume names what it announces.** `ResumeWorkflowMessage` gains an optional awaited
   activity id. Resumes that announce nothing in particular (a new run, a replay that suspended
   again) leave it empty and behave as today.
2. **A resume that arrives early waits.** When the awaited outcome is not in the journal yet,
   `ResumeWorkflowHandler` concludes nothing: it throws a dedicated core exception, and the
   transport's retry is the wait. The core stays transport-neutral: Messenger and Laravel queues
   both retry a failed message with a delay.
3. **The activity path sends immediately.** Its resume cannot carry `DispatchAfterCurrentBusStamp`,
   or the order in the processor would stay cosmetic. The other callers keep the stamp.
4. **A crash between the two steps is recovered by redelivery.** The activity message is only
   acknowledged after the append. A worker killed after the send and before the append leaves it
   unacknowledged. The transport redelivers it, the attempt runs again (at-least-once, as an
   activity already is under retries), and it sends and appends again. The early resume from the
   killed attempt waits, then either finds the outcome or gives up; see choice 1.
5. **A failed send is not a failed activity.** Today `dispatchResume()` sits inside the attempt's
   `try`, so a broker error is journalled as `ActivityTaskFailed` on an attempt that succeeded, and
   can spend the retry budget. The send moves out of it: a broker error fails the message, which
   is redelivered.
6. **The dead node goes.** `activity_transport.table_name` is removed. Setting it becomes a
   configuration error, documented in `UPGRADE.md`.

The at-least-once contract is stated in the user documentation: a resume may be delivered more
than once and before the fact it announces, and replay makes a second delivery harmless.

## Open for approval

These three shape the implementation, and each is a real trade-off. A recommendation is given;
the user decides.

1. **Liveness after the early resume gives up.** A resume that waits longer than the transport's
   retry budget (Messenger's default is 3 retries, about 7 seconds) goes to the failure transport.
   If the append then succeeds, because the worker was slow or the append sat in a
   `doctrine_transaction` the application added, no resume follows and the run is stuck.
   - *Recommended:* **also send a resume after the append.** It is idempotent, costs one message
     per activity, and makes liveness independent of any retry budget.
   - *Alternative:* document that the resume transport's retry budget must exceed the longest gap
     between send and append. That is one message fewer, and a configuration trap.
2. **A resume routed `sync`.** A synchronous transport runs the resume inline, before the append,
   every time. Neither the guide nor the benches route resumes `sync`, but an application can.
   - *Recommended:* detect it the way `DurableWorkerInspection` already does, and keep the current
     order there: append, then send. In a single process nothing is durable anyway, and the order
     is then correct.
   - *Alternative:* refuse a `sync`-routed resume at container compilation.
3. **Scope.** The signal, update, child-to-parent and timer pairs have the same gap.
   - *Recommended:* this ADR covers the activity paths (completed, failed, cancelled) that #328
     names. The other pairs get a follow-up issue that applies the same protocol: their awaited fact
     is a signal, an update, a child outcome or a fired timer, not an activity id.
   - *Alternative:* cover all of them now, in one larger change.

## Consequences

- **Port change.** `WorkflowResumeDispatcher` needs a way to send without deferral, plus the
  awaited activity id. Every implementation changes: Messenger, Laravel, Temporal (a no-op there),
  and the null one. Third-party implementers get an `UPGRADE.md` entry.
- **Message compatibility.** A `ResumeWorkflowMessage` serialized before the upgrade has no awaited
  id. Reading it back must not fail. It is handled on unserialize, and a test reads an old payload.
- **Tests.** A test kills a worker between the send and the append, on a persistent transport, and
  shows that a fresh worker completes the run. A thrown exception does not model a kill (`finally`
  blocks run, and the deferred send is discarded), so the test uses a subprocess that kills itself.
- **Temporal is unaffected.** The server owns delivery, and its dispatcher is a no-op.
- **#505** (the DBAL journal's optimistic concurrency) builds on this contract: a second resume
  of one execution is now expected, not exceptional.
