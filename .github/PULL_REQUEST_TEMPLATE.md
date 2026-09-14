<!--
The definition of done, in the one place a pull-request author reads it. It restates CLAUDE.md,
WA002 and WA006; it adds nothing to them. Delete the lines that do not apply, keep the rest.
-->

## What this changes

<!-- The defect or the gap, then the shape of the fix. The reason is the part a reviewer needs. -->

Closes #<issue> · Finding: <audit id, if any> · Parent Epic: #<epic>

## Definition of done

- [ ] **The failing test came first** and is in this diff (WA002). A change with no behaviour says so.
- [ ] **English everywhere** — identifiers, comments, tests, commit messages, this body (WA006).
      A breaking change has its `UPGRADE.md` section, in English, and a Rector rule or a script
      when one is possible.
- [ ] **`loop/guardrails/verify.sh` is green locally** — the two required CI jobs. The rest of the
      matrix is CI's to run.
- [ ] **Every commit stays under 200 changed lines** (CLAUDE.md), or a human said otherwise and it
      is quoted here.
- [ ] **Supervised paths untouched**, or the human authorisation is quoted here:
      `.github/workflows/`, `bin/splitsh-publish.sh`, `composer.json` and `composer.lock`,
      `psalm-baseline.xml`, `documentation/adr/`, `.worktrees/prises/`,
      `src/Bridge/Temporal/Api/` and `Generated/`.
- [ ] **The issue's *Done when* boxes** this pull request ticks are listed below, and the ones it
      leaves open are named.

## What this does not fix, and where that is tracked

<!-- One line per thing a reviewer might expect here and will not find. Empty is an answer. -->
