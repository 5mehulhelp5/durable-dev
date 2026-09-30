---
title: The dashboard in Magento
weight: 20
---

# The dashboard in Magento

To follow the workflows of a store from the Magento admin, install `gplanchat/durable-magento`
([Packages](../../packages/)) and open **System > Durable processes > Process history**. The page is
read-only and in English.

## Give access to a role

The screen has its own ACL resource. In **System > Permissions > User Roles**, tick **Durable
processes > Process history** on the role's resources.

## What the page offers

- **The backend state**, dated, and one line per worker role (journal and activity) when Temporal
  holds the journal.
- **Counters** per outcome, over the 200 most recent runs, whatever the grid filters say.
- **The standard admin grid**: paging (20 by default), column controls, and filters on status,
  workflow name, execution id and backend run id. The text filters look for the text anywhere in the
  value, ignoring case, among the runs of the window. A notice states the window when it is full.
- **A run page**, opened from a row: the run, its backend run, status, start and end dates,
  what it waits on, a timeline, its Nexus operations and a **Journal** table with one line per
  event (kind, phase, action, what happened). See [Read a run](../reading-a-run/).

## What it does not show

The grid does not say `waiting for a worker`, neither as a line nor as a count. The window is 200
runs: an older run does not appear, and the counters do not reach it either. See
[Parity](../parity/).

## Payloads

A payload unfolds on demand. It is masked by the preference your application declares for
`Gplanchat\Durable\Observation\PayloadRedactorInterface`; the module ships the key-name redactor.
