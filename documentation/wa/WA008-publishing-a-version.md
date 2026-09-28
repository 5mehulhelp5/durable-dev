# WA008 — Publishing a version: `bin/release.sh`, from origin/main only

## Status

Accepted — 28 September 2026 (#350, the user's decision of the same day).

## Context

Publishing a version of Durable takes two steps, and until now both lived in one person's memory:

1. a tag on a commit, pushed to origin. Splitsh propagates it to the satellite repositories, and
   Packagist follows;
2. a GitHub release for that tag, marked as a prerelease while the line is in alpha, with notes
   listing what changed since the previous version.

Each step had a trap that has already been hit:

- **A tag off origin/main.** The local `main` of a working copy often carries a commit nobody
  pushed, a prise file for instance. A tag on it publishes, to every satellite at once, a commit
  that exists nowhere else.
- **The wrong previous tag.** Asked to generate notes, `gh release create` picks the previous tag
  itself, and it sorts lexically: `v0.1.0-alpha9` comes after `v0.1.0-alpha10`. alpha10's notes
  listed alpha9's pull requests, and had to be rewritten by hand, without a compare link.

## Agreement

**A version is published with `bin/release.sh`, and never by hand.**

```bash
bin/release.sh v0.1.0-alpha15 --dry-run              # what would happen
bin/release.sh v0.1.0-alpha15                         # tag the tip of origin/main, then release
bin/release.sh v0.1.0-alpha15 <sha> --notes-file body.md
bin/release.sh v0.1.0-alpha15 --release-only          # the tag is pushed, the release failed
```

The script:

- fetches origin, and **refuses any commit that is not reachable from origin/main**. Without a
  commit, it tags the tip of origin/main;
- refuses a tag that is malformed (`vMAJOR.MINOR.PATCH`, optionally `-alphaN`, `-betaN` or
  `-rcN`) or that already exists, locally or on origin;
- finds the previous tag in **version order**, where a prerelease sorts before its release: the
  newest tag *below* the new one, so that a version on an older line is compared with its own
  line. It passes that tag to `gh release create` as `--notes-start-tag`;
- creates an annotated tag, pushes it, and then creates the release, titled `Version <x>`, with
  generated notes, marked `--prerelease` for any `-alpha`, `-beta` or `-rc` tag;
- places an editorial body above the generated notes when given `--notes-file`. Every alpha so far
  has had one, and a release is expected to keep doing so;
- finishes a release whose tag is already pushed with `--release-only`. If `gh` fails after the
  push, a plain rerun refuses the existing tag and says so. `--release-only` checks that the tag is
  on origin and on origin/main, then creates the release only.

`bin/release-test.sh` checks all of this on a throwaway repository, with a stub `gh`.

## Before and after

- **Before:** tag only a commit whose CI is green. The script checks where the commit is, not
  whether it passed.
- **After:**
  - check that every satellite received the tag;
  - update the "Latest release" line and the figures of the RFC symfony/symfony#66257, counted at
    the new tag with the method the RFC states.
