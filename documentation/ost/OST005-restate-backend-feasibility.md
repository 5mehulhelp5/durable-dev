# OST005 — Restate as a Durable backend: feasibility

## Status

Exploration — **leaning towards a time-boxed spike**. No contraindication of the kind that closed
[OST002](OST002-durable-task-backend-feasibility.md) has been established, but one candidate for it
(§4.1) is still open. Not a decision: a third backend needs an ADR (DUR005), and this document is
its input, not its substitute.

Deepens [OST001](OST001-alternative-durable-execution-backends.md) §3, which ranked Restate
"highest strategic upside, highest maintenance risk" and asked for a spike.

**A first spike already ran**: issue #464, PR #466, notes in
[`spike/restate/README.md`](../../spike/restate/README.md) (#470). Against server 1.7.12 in
request/response mode, it ran a `run`, a sleep and a promise from a hand-written PHP endpoint. It
also settled the seam (the three ports `WorkflowFiberDriver` drives, not `EventStoreInterface`),
two mappings the maintainer decided on 2026-09-24 (side effect → `run`, activity → `CallCommand`),
and the licence: the server is BSL 1.1 with an Additional Use Grant for your own services; the
protocol is MIT. This document takes those as given and covers what that spike left open.

## Method

Read on 2026-09-28 from `restatedev/restate` at `9d50162`:
`service-protocol/dev/restate/service/protocol.proto` (816 lines, protocol v7),
`crates/types/src/service_protocol.rs` and `crates/invoker-impl/`. Behaviour the proto does not
state is quoted from the Restate documentation. Durable's side is read from
`WorkflowCommandBufferInterface`, `WorkflowHistorySourceInterface`, `ExecutionContext` and
`WorkflowFiberDriver`.

The test applied to every gap is OST002's: does the backend **refuse by name**, or does it
**silently do something else**? Only the second kind closes an option. The settled ground for it is
the user documentation's principle that a missing capability "fails explicitly rather than being
silently ignored", and DUR036's Nexus refusal. DUR051, still proposed, generalises that refusal to
every port method; "refused by name" below means that shape.

---

## 1. Two corrections to OST001

- **`ext-grpc` is not the differentiator any more.** The Temporal bridge already runs without it:
  `Http\CurlGrpcTransport` (curl, HTTP/2), `Http\GuzzleGrpcTransport`, and the JSON gateway. The CI
  job "Sans ext-grpc" checks the extension is absent, then runs the `integration,temporal` suites
  over gRPC on curl. What Restate changes is **the direction of control**: the server calls the
  application over HTTP for every step. There is no long-lived poller, and in request/response mode
  (HTTP/1.1) each step is an ordinary PHP-FPM request.
- **A PHP SDK now exists.** [`qcodr/restate-sdk-php`](https://github.com/qcodr/restate-sdk-php),
  created June 2026, implements protocol v5 to v7 in pure PHP, with bidirectional HTTP/2, a PSR-15
  adapter and a Lambda handler. It is recorded as prior art, not proposed as a dependency.

## 2. What maps well

| Durable | Restate | Note |
|---|---|---|
| `sideEffect()` | `RunCommandMessage` + `ProposeRunCompletionMessage` | In-process, journaled, exactly the primitive. OST002 §2.2 does not arise. |
| `version()` | a `Run` carrying the version | Same channel as side effects. |
| `activity()` | `CallCommandMessage` to an activity service | Own invocation, `idempotency_key`, its own deployment (≈ a task queue); `limit_key` (v7) for concurrency. Restate retries per handler, in the manifest, not per call, so `ActivityOptions` does not map directly: who retries is still open (`spike/restate/README.md`, options 1 and 2). |
| `timer()` | `SleepCommandMessage` | No command cancels a sleep; the interpreter ignores its late completion, as the journal backend already does. |
| `cancelActivity()` | `SendSignalCommandMessage`, `idx = CANCEL`, on the call's invocation id (v5+) | An activity not yet started **does not run**. One already running finishes, like a Temporal activity that does not heartbeat. Weaker than Temporal only for heartbeating activities, and not silent: see §3. |
| child workflows, `ParentClosePolicy` | `Call` to a workflow target; cancellation propagates down the call graph natively | *Abandon* is a `OneWayCallCommandMessage`. |
| cron | `OneWayCallCommandMessage.invoke_time` | Delayed send, native. |
| replay | request/response mode resends the whole journal after each suspension | This is the fiber-replay model Durable already runs. |

Failures carry `Failure.metadata` (v6), a string map for Durable's failure envelope.
`ErrorMessage.behavior` (v7) lets the SDK choose retry, pause or fail per error.

## 3. What would be lost, and refused by name

| Capability | Why | Refusal |
|---|---|---|
| Activity heartbeats | Nothing in the protocol. In request/response mode nothing can reach a request already running. | `ActivityHeartbeatSenderInterface` refuses. |
| Nexus across clusters | Within one cluster a `CallCommand` covers it (first spike); there is no cross-cluster endpoint registry. | The cross-cluster case refused, as on the journal backend (DUR036). |
| Arbitrary queries | A shared handler reads key/value state; it cannot run workflow code against the run's memory. | Queries become a projection the workflow writes with `SetStateCommandMessage`. |
| `WorkflowIdReusePolicy` beyond reject-duplicate | A workflow key runs once, "Previously accepted" until retention expires. | Other policies refuse, unless §4.3 lands. |

## 4. Open questions — the scope of the spike

### 4.1 Workflow cancellation: the one candidate for a silent change

Durable delivers a workflow's cancellation **at the await it is suspended on**.
`WorkflowFiberDriver` cancels what that await was waiting for, and nothing else:
`cancelPending()` calls `AwaitableCancellation::cancelUnsettled()`, which walks only the awaitable
the fiber is suspended on and its composite members. It then records
`WorkflowCancellationDelivered` with those targets, and throws `WorkflowCancelledFailure` into the
fiber there. An activity that was started but not awaited keeps running; the workflow decides.

Restate's built-in `CANCEL` does the opposite order: it "first reaches the leaves of the call
graph", so **every** call in flight is cancelled before the workflow sees a `TerminalError` at its
next await.

- **Through Durable's client**, the bridge sends its own named signal instead of `CANCEL`, and
  Durable's semantics hold.
- **Through Restate's UI, CLI or admin API**, `CANCEL` bypasses that: activities the workflow never
  awaited get cancelled too. Nothing fails, nothing warns.

That is the shape of the finding that closed OST002, on a narrower surface: only unawaited
activities, only when cancelled from outside Durable. The spike has to say whether the bridge can
detect a native `CANCEL` and report it, or whether this needs to be documented as an operational
rule ("cancel through Durable, never through Restate").

### 4.2 Workflow updates

Candidate: a shared handler sends a named signal (`SendSignalCommandMessage.name`) to the `run`
invocation and waits on an awakeable for the reply. Two things are unproven: that several named
signals to one invocation keep their relative order, and that the reply path survives a
suspension. The same kind of hypothesis as OST002 §7.1. Signals, too, would need an ordered
mailbox; the same proof covers both.

### 4.3 One run per key

A Restate workflow runs once per key. `continueAsNew` and any reuse policy past reject-duplicate
need **run-level keys** and a lookup from workflow id to current run (a virtual object is the
natural home). Every signal addressed by workflow id goes through that lookup. More than wiring:
it is a second source of truth next to the journal.

### 4.4 Cost of request/response mode

- The whole journal travels at every suspension. Measure bytes per step on a long workflow.
- FPM's `max_execution_time` and `request_terminate_timeout` cap how long an activity can run, and
  Restate's `inactivity_timeout` is ignored in this mode. Long activities need the HTTP/2 mode, which
  means a long-lived PHP server again.

### 4.5 Protocol churn

`MIN_DISCOVERABLE_SERVICE_PROTOCOL_VERSION = V5`: an endpoint speaking v1 to v4 can no longer
register a new deployment, and v3 and v4 were yanked. Running invocations stay safe
(`MIN_INFLIGHT = V1`). OST001 reported v7 behind `RESTATE_EXPERIMENTAL_ENABLE_PROTOCOL_V7`; the
invoker code read here handles v7 without a visible guard, but the read was partial, so v7's status
is **undetermined**. Upstream still publishes no deprecation window. The spike should track which
versions each Restate release accepts, over at least two releases.

## 5. Position

A better fit than Durable Task on the point that closed it: side effects are native and
cancellation is a real protocol feature. The losses (heartbeats, cross-cluster Nexus, arbitrary
queries, most reuse policies) are all refusals by name. One silent change is possible, §4.1, and it is narrower
than OST002's.

**Next step, if wanted:** a spike of bounded length that answers §4.1 to §4.4 against a real
Restate server, with the churn watch of §4.5 running alongside. No production code, no dependency,
until an ADR decides.

## References

- [`spike/restate/README.md`](../../spike/restate/README.md) — the first spike (#464, #466, #470)
- [`restatedev/restate` — `service-protocol/`](https://github.com/restatedev/restate/tree/main/service-protocol)
- [Restate: request lifecycle](https://docs.restate.dev/guides/request-lifecycle) · [managing invocations, cancellation](https://docs.restate.dev/services/invocation/managing-invocations) · [workflows](https://docs.restate.dev/tour/workflows)
- [`qcodr/restate-sdk-php`](https://github.com/qcodr/restate-sdk-php)
- [OST001](OST001-alternative-durable-execution-backends.md) — the survey this deepens
- [OST002](OST002-durable-task-backend-feasibility.md) — the study whose test this applies
- [DUR036](../adr/DUR036-nexus-caller-only-and-the-backend-asymmetry.md), [DUR051](../adr/DUR051-a-backend-refuses-what-it-cannot-honour.md)
