#!/usr/bin/env bash
#
# Check of `release.sh` on a throwaway repository, with a stub `gh` that records its arguments:
# it tags the tip of origin/main and pushes the tag, creates the release with the previous tag in
# version order (alpha9 before alpha10, which lexical order gets wrong), and refuses a tag that is
# malformed, already taken, or aimed at a commit that is not on origin/main.
set -uo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

failures=0
check() { # $1 description · $2 command that must succeed
    if eval "$2"; then echo "  ok        $1"; else echo "  FAIL      $1"; failures=$((failures + 1)); fi
}

g() { git -c user.name=t -c user.email=t@t -c init.defaultBranch=main "$@"; }

# A stub `gh`: records every call, one line of arguments per call.
mkdir -p "$TMP/bin"
cat > "$TMP/bin/gh" <<'STUB'
#!/usr/bin/env bash
printf '%s\n' "$*" >> "$GH_LOG"
STUB
chmod +x "$TMP/bin/gh"
export PATH="$TMP/bin:$PATH" GH_LOG="$TMP/gh.log"

g init -q --bare "$TMP/origin.git"
g clone -q "$TMP/origin.git" "$TMP/repo" 2>/dev/null
cd "$TMP/repo" || exit 1
for n in 8 9 10; do
    g commit -q --allow-empty -m "alpha$n"
    g tag -a "v0.1.0-alpha$n" -m "Version 0.1.0-alpha$n"
done
g commit -q --allow-empty -m "after alpha10"
g push -q origin main --tags
g checkout -q -b side && g commit -q --allow-empty -m "not on main" && g checkout -q main
main_sha="$(git rev-parse origin/main)"
side_sha="$(git rev-parse side)"

release() { bash "$ROOT/bin/release.sh" "$@" 2>&1; }

output="$(release v0.1.0-alpha11)"
check "the new tag is on origin"                     '[ "$(git ls-remote origin "refs/tags/v0.1.0-alpha11^{}" | cut -f1)" = "$main_sha" ]'
check "the release names alpha10 as the previous tag" 'grep -q -- "release create v0.1.0-alpha11 .*--notes-start-tag v0.1.0-alpha10" "$GH_LOG"'
check "an alpha is a prerelease"                      'grep -q -- "--prerelease" "$GH_LOG"'
check "the notes are generated"                       'grep -q -- "--generate-notes" "$GH_LOG"'

: > "$GH_LOG"
output="$(release v0.1.0-alpha12 "$side_sha")"; status=$?
check "a commit not on origin/main is refused"       '[ "$status" -ne 0 ] && grep -qi "not on origin/main" <<<"$output"'
check "nothing is tagged for it"                      '[ -z "$(git ls-remote origin refs/tags/v0.1.0-alpha12)" ]'
check "no release is created for it"                  '[ ! -s "$GH_LOG" ]'

output="$(release v0.1.0-alpha11)"; status=$?
check "a tag already taken is refused"                '[ "$status" -ne 0 ] && grep -qi "already exists" <<<"$output"'

output="$(release 0.1.0-alpha13)"; status=$?
check "a malformed tag is refused"                    '[ "$status" -ne 0 ] && [ -z "$(git ls-remote origin refs/tags/0.1.0-alpha13)" ]'

: > "$GH_LOG"
output="$(release v0.1.0-alpha13 --dry-run)"; status=$?
check "a dry run succeeds"                            '[ "$status" -eq 0 ]'
check "a dry run tags nothing"                        '[ -z "$(git ls-remote origin refs/tags/v0.1.0-alpha13)" ] && [ -z "$(git tag -l v0.1.0-alpha13)" ]'
check "a dry run creates no release"                  '[ ! -s "$GH_LOG" ]'

# Publishing onto an older line: the previous tag is the newest one below the new tag, not the
# newest tag of the repository.
g tag -a v0.0.1 -m "Version 0.0.1" "$(git rev-list --max-parents=0 origin/main)" && g push -q origin v0.0.1
: > "$GH_LOG"
output="$(release v0.0.2)"
check "an older line names its own previous tag"      'grep -q -- "release create v0.0.2 .*--notes-start-tag v0.0.1" "$GH_LOG"'

# A release that failed after the tag was pushed is finished with --release-only: the tag is
# kept, only the release is created.
: > "$GH_LOG"
output="$(release v0.1.0-alpha11 --release-only)"; status=$?
check "--release-only finishes an existing tag"       '[ "$status" -eq 0 ] && grep -q -- "release create v0.1.0-alpha11 .*--notes-start-tag v0.1.0-alpha10" "$GH_LOG"'
: > "$GH_LOG"
output="$(release v0.1.0-alpha14 --release-only)"; status=$?
check "--release-only refuses a tag not on origin"    '[ "$status" -ne 0 ] && [ ! -s "$GH_LOG" ]'

[ "$failures" -eq 0 ] || { echo "$output"; exit 1; }
