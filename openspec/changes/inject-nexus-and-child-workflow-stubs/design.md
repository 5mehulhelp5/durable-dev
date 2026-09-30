## Context

`WorkflowDefinitionLoader` builds a workflow's arguments once, at registration, in
`planArguments()`: one closure per parameter, which later produces the argument from the
environment and the input. Two kinds of parameter are supplied today, `WorkflowEnvironment` and an
`ActivityStub` marked `#[Activities]`. `isInjected()` answers "does the loader supply this
parameter?" for every other reader of a workflow signature:

- `inputParameters()`, the parameters a caller passes when it starts the workflow;
- `ChildWorkflowStub::argumentsToInput()`, the payload a parent sends to a child;
- `NexusFulfilmentParameterNames`, the names a fulfilling workflow must share with its contract.

Every host registers workflows through `WorkflowRegistry::registerClass()`, which calls the
loader: the Symfony bundle's `WorkflowPass` (Sylius included), Laravel's `DurableServiceProvider`,
Magento's `RuntimeFactory`. What the loader supplies, every host supplies.

`WorkflowEnvironment::nexusStub(string $contract, NexusEndpoint|string $endpoint, ?NexusOperationTimeouts $timeouts)`
requires the endpoint. `WorkflowEnvironment::childWorkflowStub(string $class, ?ChildWorkflowOptions $options)`
fixes the options at construction. When `workflowId` is null, the child gets a UUID v7 generated
on the first pass (`ExecutionContext::uuid()`); a replay reads it back from the journal instead of
generating a new one.

## Goals / Non-Goals

**Goals**

- A workflow method receives Nexus stubs and child workflow stubs as arguments, the way it receives
  activity stubs.
- A Nexus endpoint stays a deployment fact: the attribute may name one, the host configuration
  names it otherwise.
- A child's workflow id can depend on the input without giving up the injected stub.
- A mistake fails at registration, naming the parameter, as `#[Activities]` does.

**Non-Goals**

- Removing or deprecating the explicit `nexusStub()` and `childWorkflowStub()` calls.
- Per-call memo or search attributes on a child.
- Any change to the commands sent to Temporal or to the events journaled.

## Decisions

### Two attributes, named after what the stub gives access to

`#[NexusOperations(Contract::class, ...)]` on a `NexusStub` parameter, and
`#[ChildWorkflow(Workflow::class, ...)]` on a `ChildWorkflowStub` parameter.

`#[Activities]` names what the stub schedules; `NexusOperations` follows it. `ChildWorkflow` is
singular because one stub starts executions of one workflow class.

Rejected: a single `#[Stub(Contract::class)]` whose meaning depends on the parameter's type. It
saves one attribute and loses the per-kind options: a Nexus stub takes operation bounds, a child
takes a parent close policy, and one attribute carrying both sets would accept combinations that
mean nothing.

### Attribute arguments are constants, so options are scalars

As in `#[Activities]`, durations are seconds and enums are cases:

```php
#[NexusOperations(StockContract::class, endpoint: 'demo-shop-stock', scheduleToClose: 300.0)]
NexusStub $stock,

#[ChildWorkflow(ShipWorkflow::class, parentClosePolicy: ParentClosePolicy::Abandon, executionTimeout: 86400.0)]
ChildWorkflowStub $ship,
```

`#[NexusOperations]` takes `endpoint`, `scheduleToClose`, `scheduleToStart`, `startToClose`.
`#[ChildWorkflow]` takes `parentClosePolicy`, `taskQueue`, `namespace`, `workflowIdReusePolicy`,
`executionTimeout`, `runTimeout`, `taskTimeout`, `staticSummary`. The value objects
(`NexusOperationTimeouts`, `WorkflowTimeouts`, `ChildWorkflowOptions`) are built from them at
registration, so their own checks run there. `NexusOperationTimeouts` already refuses bounds the
server would clamp silently; that refusal moves from the first call to registration.

### The endpoint: attribute first, then host configuration, else a registration error

```yaml
# config/packages/durable.yaml
durable:
    nexus:
        endpoints:
            Gplanchat\Durable\Demo\Contracts\Stock\StockContract: demo-shop-stock
```

Resolution order for an injected stub: the attribute's `endpoint`, then the configured endpoint for
that contract, then an error at registration that names the parameter, the contract, and the
configuration key of the host in use.

The resolver is a port of the core, `NexusEndpointResolver`, with one method from contract class to
endpoint or null. Each host builds it from its own configuration and hands it to the registry's
loader. The loader resolves at registration, so a missing endpoint is found at container
compilation on Symfony, at boot on Laravel, at `RuntimeFactory::create()` on Magento, and never by
the first workflow that runs.

`$env->nexusStub()` gets the same fallback: `$endpoint` becomes optional, and when it is omitted
the environment asks the same resolver. That call runs inside a workflow, so a missing endpoint
there fails at the call, naming the contract and the configuration key. Making the parameter
optional is additive: every existing call passes it.

Rejected: an endpoint named after the contract by convention (for example the service name). The
service name is declared by the contract, and the endpoint is chosen by whoever deploys; tying one
to the other is the coupling the docblock warns against.

Rejected: resolving the endpoint at run time, on every call. It would make a configuration mistake
a runtime failure of the first execution that reaches the call, possibly days after a deploy.

### A per-call workflow id: `withWorkflowId()`

```php
return $env->await($ship->withWorkflowId("ship-{$orderId}")->run($orderId, $slot));
```

`withWorkflowId(string $id): static` returns a new stub whose options are the injected ones with
`workflowId` replaced. The injected stub is unchanged, so a workflow can start several children
from one parameter.

The id is computed by workflow code from the input, so a replay computes the same one. It travels
in the command exactly as an id set in `ChildWorkflowOptions` does today; nothing new is journaled.

The stub dispatches the child's entry method through `__call`. A real method named
`withWorkflowId` shadows an entry method of the same name. When a workflow declares a
`#[ChildWorkflow]` parameter for a class whose entry method has that name, registering that
workflow fails, naming the parameter, the child class and the method. The child class itself stays
valid: it can still be started by hand, or run as a top-level workflow.

Rejected for now: `withOptions(ChildWorkflowOptions)`. It would cover memo and search attributes
computed at run time, and it would also let a call override the policy the attribute declared,
which makes the attribute a default rather than a declaration. The explicit
`$env->childWorkflowStub($class, $options)` already covers that case. It can be added later without
breaking anything.

### One place decides what is injected

`isInjected()` gains the two parameter types, so the three readers listed in Context stop counting
them as input without any change of their own. A parameter typed `NexusStub` or
`ChildWorkflowStub` without its attribute is refused at registration, as an `ActivityStub` without
`#[Activities]` is today, because the loader cannot tell which contract or class to stub.

### PHPStan

`StubMethodsExtension` already resolves `NexusStub<Contract>` and `ChildWorkflowStub<Workflow>`.
`ActivitiesParameterRule` is generalised to the three attributes: the `@param` docblock must name
the class the attribute names, and a missing one is reported under the same ignorable identifier
family.

## Probed and assumed

Nothing in this change is sent to the server differently, so no server behaviour is newly relied
on.

- **Assumed, unchanged:** a child started with an explicit workflow id behaves on Temporal as it
  does today with `ChildWorkflowOptions::$workflowId`. `withWorkflowId()` produces the same
  command.
- **Assumed, unchanged:** the server clamps Nexus bounds it does not accept.
  `NexusOperationTimeouts` already encodes what was probed about that; this change only calls it
  earlier.
- **To probe in the tasks:** that a workflow using the three injected stubs together replays to the
  same commands as the same workflow with hand-built stubs, on the Temporal backend. This is a
  regression check, not a new server rule.

## Risks

- **An endpoint that changes under an execution in flight.** An operation scheduled before a
  deploy is recorded with its endpoint. If the configuration maps the contract to another endpoint
  after the deploy, a replay of that execution resolves the new one. Whether that is harmless
  depends on how replay matches a recorded Nexus schedule against the command it rebuilds: if the
  endpoint takes part, the replay diverges. Changing a hard-coded endpoint in a deploy has the same
  effect today; configuration makes the change easier to make. The tasks probe it (2.5). If the
  endpoint takes part in the match, the rule to document is the one that already applies to any
  deploy that changes workflow code: an endpoint in use by executions in flight is not changed in
  place; a new endpoint is added and the old one kept until they finish.
- **Several stubs for one contract** are allowed: two parameters may name the same contract with
  two endpoints, and each keeps its own. Nothing is shared between them.

- **A configuration key on three hosts** is three places to document and test. The tasks require
  one test per host that resolves an endpoint from configuration.
- **An endpoint in the attribute** stays possible, and it is what every current example does.
  The documentation shows the configuration form first, and explains why the endpoint is a
  deployment fact.
