---
title: Use cases
weight: 45
---

# Use cases

The rest of this guide is reference material: one page per feature, `await` here, signals there,
Nexus further on. This section works the other way round. Each entry is **a whole thing**: several
applications, several mechanisms, a problem that exists outside Durable, with its code in the
repository and a way to run it.

The entries are not advanced exercises, and none of them is hard. What separates an entry from an
example in [Writing a workflow](../workflows/) is that it is *complete*.

| | |
|---|---|
| [Four applications calling each other](nexus-demo/) | three frameworks, four Temporal namespaces, one shared contract, and an execution that serves one operation while calling another |

A second entry, an interruptible AI agent whose loop is driven from workflow code, is waiting for its
prototype to settle. It will land with a way to run it, because an entry must point at something
you can start.

## What an entry must contain

To keep the section readable as it grows, every page says, in this order:

1. **the problem**, stated without the word "Durable";
2. **what was built**: the files, and where they live;
3. **what Durable brings**, and above all **what it does not**;
4. **how to run it**;
5. **what is not proven.** An entry without that part only lists benefits.
