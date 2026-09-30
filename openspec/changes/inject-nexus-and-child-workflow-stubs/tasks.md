# Tasks

Every code task is TDD (WA002): the failing test comes first, in the same commit as the code that
makes it pass. Each commit stays under 200 changed lines.

## 0. Decide before building

- [ ] 0.1 Draft the ADR that records the endpoint resolution order (attribute, then host
      configuration, then a registration error) and the per-call child workflow id. An ADR is the
      user's decision (DUR000): the draft goes to review, not to `main`, until accepted.

## 1. Core: what the loader supplies

- [ ] 1.1 `isInjected()` covers a `NexusStub` parameter with `#[NexusOperations]` and a
      `ChildWorkflowStub` parameter with `#[ChildWorkflow]`. Test: a workflow with both, plus an
      input parameter, has exactly one input parameter; a parent passes only that one to it as a
      child; its Nexus fulfilment parameter names ignore the two stubs.
- [ ] 1.2 The `#[NexusOperations]` attribute, with `endpoint`, `scheduleToClose`,
      `scheduleToStart` and `startToClose`. `planArguments()` builds the stub at registration.
      Tests: the stub schedules operations on the attribute's endpoint; an impossible bound fails at
      registration, naming the parameter.
- [ ] 1.3 The `NexusEndpointResolver` port and its in-memory implementation. The loader asks it
      when the attribute names no endpoint. Tests: the configured endpoint is used; no endpoint in
      either place fails at registration, naming the parameter and the contract.
- [ ] 1.4 `$env->nexusStub()` with the endpoint optional, falling back to the same resolver.
      Tests: an explicit endpoint still wins; an omitted one is resolved; an unresolved one fails at
      the call, naming the contract.
- [ ] 1.5 The `#[ChildWorkflow]` attribute, with `parentClosePolicy`, `taskQueue`, `namespace`,
      `workflowIdReusePolicy`, `executionTimeout`, `runTimeout`, `taskTimeout`, `staticSummary`.
      Tests: a child started through the injected stub carries those options; a class that is not
      a workflow fails at registration.
- [ ] 1.6 `ChildWorkflowStub::withWorkflowId()`. Tests: the child starts under that id; the stub it
      was called on keeps its own id; a replay starts no second child; a child class whose entry
      method is named `withWorkflowId` is refused at registration.
- [ ] 1.7 A `NexusStub` or `ChildWorkflowStub` parameter without its attribute is refused at
      registration, as an `ActivityStub` without `#[Activities]` is.
- [ ] 1.8 `WorkflowEnvironment` receives the endpoint resolver as a new last constructor argument,
      after `$nexusContractResolver` (the comment on that argument says why a new one goes last),
      for `nexusStub()` without an endpoint. `ExecutionEngine::createEnvironment()` and
      `WorkflowTaskRunner` pass it. Test: `$env->nexusStub()` without an endpoint schedules on the
      configured one, on the memory backend and on the Temporal backend.

## 2. Hosts: the endpoint configuration

- [ ] 2.1 Symfony: `durable.nexus.endpoints`, a map from contract class to endpoint, validated at
      container compilation (the class exists and is a Nexus contract). Test through the bundle's
      container.
- [ ] 2.2 Laravel: `nexus.endpoints` in `config/durable.php`, beside `nexus.handlers`. Test through
      `DurableServiceProvider` on the memory backend.
- [ ] 2.3 Magento: a `nexusEndpoints` argument of `RuntimeFactory`, declared in `di.xml` beside
      `nexusHandlers`. Test through `RuntimeFactory::create()`.
- [ ] 2.4 One workflow using the three injected stubs replays to the same commands as the same
      workflow with hand-built stubs, on the Temporal backend (integration suite, real server).
- [ ] 2.5 Probe, on a real server: an execution waits on a Nexus operation; the configuration is
      changed to map its contract to another endpoint; the worker restarts and the execution
      replays. Record whether the replay diverges, and document the rule that follows (design.md,
      Risks).
- [ ] 2.6 Symfony wiring: `WorkflowPass` builds its compile-time loader with a resolver read from
      the `durable.nexus.endpoints` parameter, and the `durable.workflow_definition_loader`
      service receives the same resolver, so the registry, `ExecutionEngine` and the Temporal
      assembly share it. Test: a missing endpoint fails the container compilation.
- [ ] 2.7 Laravel wiring: the `WorkflowRegistry` singleton in `bindWorkflowRegistry()` is built
      with the `WorkflowDefinitionLoader` singleton, which receives the resolver. Test: resolving
      the registry fails, naming the parameter, when the endpoint is missing; resolving it
      succeeds when `nexus.endpoints` names it.
- [ ] 2.8 Magento wiring: the registry built in `create()` and the one built in `assembly()` each
      receive a loader with the resolver, and `assembly()` passes that loader to
      `TemporalRuntimeAssembly` instead of a second `new WorkflowDefinitionLoader()`. Tests: the
      memory path through `create()`; the Temporal path through the first call that builds the
      assembly.

## 3. PHPStan

- [ ] 3.1 The parameter rule covers `#[NexusOperations]` and `#[ChildWorkflow]`: a `@param`
      docblock naming another class than the attribute is an error, a missing one is reported under
      the same ignorable identifier family as for `#[Activities]`.

## 4. Documentation, EN and FR

- [ ] 4.1 Nexus: the calling example takes its stub as an argument, with the endpoint in
      configuration; the attribute form of the endpoint is shown second, with why the endpoint is a
      deployment fact.
- [ ] 4.2 Workflows: the child workflow examples take their stub as an argument, with
      `withWorkflowId()` for an id computed from the input. "When to build the stub yourself" loses
      its Nexus and child case and keeps the per-call memo and search attributes case.
- [ ] 4.3 Configuration: the three endpoint keys, one per host.
- [ ] 4.4 A second model reviews the pages before the PR (CLAUDE.md, dispatch rule 3).

## 5. Close

- [ ] 5.1 `loop/guardrails/verify.sh` passes.
- [ ] 5.2 The Rector ticket for Nexus and child stubs is opened, following #778.
