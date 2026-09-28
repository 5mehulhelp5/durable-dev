# Claims in progress

Several sessions share this checkout. A slice is claimed by **creating its file** in `prises/`,
**before** starting, and the file is deleted at merge. Its absence is what let two sessions cross
paths on `workflow-conditions-and-handler-dispatch` on 2026-08-26.

The file's path **is** the branch name: `prises/test/bundle-integration-suite.md` for the branch
`test/bundle-integration-suite`. Nothing to choose, nothing to write twice.

## Why a file and not a line

The registry was a table in this file until 2026-08-27, and the table was a magnet for conflicts:
three rebases in one hour on a single PR, each time because another session had touched **another
line** of the same table. Two sessions never write the same claim — that is the whole point of the
registry — but git only sees one file, and two neighbouring lines are enough to make it stop.

One file per claim removes the conflict by construction: adding a claim creates a file, releasing
it deletes it. Two sessions only step on each other if they take **the same branch** — and then git
stops on an add/add conflict, which is exactly the service the registry is expected to give.

## What the mechanism does not do on its own

**A claim is pushed to `main` before starting, and released there when closing.** A file created in
a worktree exists only there: nobody sees it before the merge, that is, once the work is done. On
2026-08-26, nine slices were built twice for that reason — the whole block 4 of
`temporal-nexus-support` twice, the same class under the same name, the same guard written
identically. The registry only prevents a collision if it is read **and written** on `main`.

> **And the repository allows it.** The protection of `main` only requires status checks
> (`QA (CS + tests)` 8.2→8.5, `Analyse statique`, `strict: true`) and leaves `enforce_admins` at
> `false`: the owner pushes directly. The `docs(prises):` commits in the history are there to prove
> it — `d0f614d`, `8180b45`, `a04420b` have no merge commit.
>
> Sending a claim through a PR makes it visible **after** the fact, several minutes later. That is
> exactly the delay the paragraph above blames for nine slices built twice. A session that forbids
> itself the direct push by convention should know what it trades for what, rather than believe in
> a technical limit.

And a claim that is not released lies for as long as it stays. On 2026-08-27, the registry still
announced four slices "in progress" or "in review" on an **archived** change, whose four branches
had long vanished from the remote. A stale registry is worse than an empty one: it makes you give up
a free slice. **Releasing your claim is part of the merge**, just like deleting your branch and
removing your worktree.

## The check, and what it does not cover

`bin/prises-check.sh` catches stale claims. It runs on every PR that touches the registry, and once
a day for the rest — a forgotten release shows up in no PR, by definition.

**The criterion is the PR, not the branch.** A claim is placed *before* the branch exists on the
remote: comparing it to live branches would turn it red on the normal case, and a check that turns
red on the normal case gets disarmed within the week. A claim is stale when its branch has at least
one closed PR and **no** open PR.

It also checks that the file's title names the same branch as its path, because the registry is
read by eye as much as by a script.

**What it does not see:** a live claim deleted by mistake. On 2026-08-27, a rebase older than a
claim very nearly swept it away — the table conflicted, but the resolution had become mechanical
from being always the same. Nothing would have turned red, and it was the session holding the claim
that saw it. If you delete a claim, look at `main` when closing rather than assume it.

## The shape of a claim file

```markdown
# <branch>

- **Scope**: what is being done, in one line
- **Entries**: the files or directories touched
- **State**: in progress | in review
```

## Reading the registry

The path carries the branch's slash, so one directory per prefix (`change/`, `docs/`, `fix/`…).
`ls` then only shows the prefixes; `find` gives back the overview the table gave at a glance:

```
find .worktrees/prises -name '*.md'
```
