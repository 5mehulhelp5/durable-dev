---
title: Why Durable
weight: 1
---

# Why Durable

Some work does not fit in a request. Charging a card, reserving the stock and emailing a receipt is
one business operation, but it touches three systems, takes longer than a connection stays open, and
can be interrupted between any two steps by a deploy, a crash or an OOM kill.

PHP has no built-in answer for that, so every codebase builds its own. Durable provides that answer
as a library, written once.

## You already have this problem if

Look for these in your own code. Each of them is a hand-built piece of durable execution:

- a **status column that means *maybe*** (`pending`, `processing`, `in_progress`), and nobody is
  sure which rows are stuck;
- an **idempotency key** you wrote yourself, because a retry charged a customer twice once;
- a **reconciliation job** that runs nightly to find the operations that stopped halfway;
- a **repair script**, run by hand in production, when a batch dies in the middle;
- a **retry counter and a dead-letter table**, plus the runbook that says what to do with them;
- no way to answer *why did order 4242 stop three days ago* except reading logs.

Those six exist so that a process survives an interruption. Durable makes the process itself
survive. The runtime records each completed step in a **journal** (the append-only record of
everything an execution decided and received; see the [glossary](../glossary/)). After a restart,
it replays the method and returns the recorded results instead of running those steps again. The
process resumes on the line it was on.

You can redeploy a worker (the process that runs workflows and activities) mid-process. No
completed step runs again and nothing is lost, without any cron job.

## What it replaces

One method, and the journal behind it, instead of:

| You maintain today | What answers it instead |
|---|---|
| A state column and the migration that adds the next state | The line the method is on. The journal holds the position |
| A scheduler that polls for what is due | The next statement; timers and signals wake the execution |
| A retry counter and a dead-letter table | `RetryLimit::ofAttempts(3)`, an option on the activity stub |
| Idempotency keys, so a retry does not double-charge | A recorded step returns its recorded result and does not run again. An attempt cut off before its result is recorded is retried: the call it makes still needs [a stable key](../activities/#idempotency) |
| Reading logs to learn why an execution stopped | Replay its journal: every step, every result, every attempt |

The [home page](/) walks through the same order, step by step, showing what happens with and
without. It is the fastest way to see the mechanism if you have five minutes and no code in front
of you.

## When you do not need it

Durable is not free: it adds a journal to write, workers to run, and a determinism rule your
workflow code has to respect. Skip it when:

- the work **fits in one request** and has no external side effect worth recovering: rendering a
  page, a search query, a report you can run again;
- the work **can safely restart from scratch**. A nightly export that rewrites the whole file loses
  nothing by being retried from the top; a partial charge does;
- your queue consumers are **already idempotent and already observable**, and you can answer *what
  happened to this job* without opening a log file. In that case you have already built what Durable
  provides;
- there is **exactly one side effect**. A single `INSERT` in a transaction is already atomic. The
  problem starts at the second step, when the first one has already happened and cannot be rolled
  back.

To decide, look at what losing your place mid-process costs. If it costs money, stock or a
customer's trust, give the process a journal. If it costs a re-run, you do not need one.

## Where to go next

| | |
|---|---|
| [Concepts](../concepts/) | the vocabulary (workflow, activity, journal, replay) before the guides |
| [Getting started](../getting-started/) | install, configure, and write a first workflow |
| [Packages](../packages/) | what to install for your framework, and which backend |
| [Durable and the Temporal PHP SDK](../comparison/) | if you have decided on durable execution and are choosing between the two |

The comparison assumes you have already made the decision this page is about, so read it after
this one.
