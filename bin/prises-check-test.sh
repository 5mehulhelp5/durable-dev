#!/usr/bin/env bash
#
# Check of the check. `prises-check.sh` has three verdicts and queries a remote service: its errors
# therefore only show up in production of the registry, and the first one cost a false positive on
# a live claim. This file makes them show up here.
#
# `gh` is replaced by a script that answers from a scenario, which makes the cases reproducible and
# the test offline.
set -uo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

failures=0

# $1 case name · $2 PR states (empty, "open", "closed"…) · $3 ahead_by ("" = missing branch)
# $4 expected outcome: "live" or "stale"
# $5 branch prefix (default "chore") · $6 state of the OpenSpec change: "" | "left" | "done"
case_() {
    local name="$1" states="$2" ahead="$3" expected="$4" prefix="${5:-chore}" change="${6:-}"
    local box="$TMP/$name"
    mkdir -p "$box/.worktrees/prises/$prefix" "$box/bin"

    printf '# %s/%s\n' "$prefix" "$name" > "$box/.worktrees/prises/$prefix/$name.md"

    if [ -n "$change" ]; then
        mkdir -p "$box/openspec/changes/$name"
        if [ "$change" = "left" ]; then
            printf -- '- [x] 1.1 done\n- [ ] 1.2 not yet\n' > "$box/openspec/changes/$name/tasks.md"
        else
            printf -- '- [x] 1.1 done\n- [x] 1.2 done\n' > "$box/openspec/changes/$name/tasks.md"
        fi
    fi
    cp "$ROOT/bin/prises-check.sh" "$box/bin/"

    # The fake `gh`: it only knows the two calls the script makes.
    cat > "$box/gh" <<FAKEGH
#!/usr/bin/env bash
for arg in "\$@"; do
    case "\$arg" in
        *pulls?head=*)
            for e in $states; do echo "\$e"; done
            exit 0 ;;
        */compare/*)
            [ -z "$ahead" ] && exit 1
            echo "$ahead"
            exit 0 ;;
    esac
done
exit 0
FAKEGH
    chmod +x "$box/gh"

    local output
    output="$(cd "$box" && PATH="$box:$PATH" bash bin/prises-check.sh dummy/repo 2>&1)"
    # The summary line always contains the word "stale": the error line is the one to read, not
    # the count. First false positive of this file, fixed here.
    local actual="live"
    grep -q '::error file=' <<<"$output" && actual="stale"

    if [ "$actual" = "$expected" ]; then
        printf '  ok        %-34s %s\n' "$name" "$expected"
    else
        printf '  FAIL      %-34s expected %s, got %s\n' "$name" "$expected" "$actual"
        sed 's/^/            /' <<<"$output"
        failures=$((failures + 1))
    fi
}

# The case that motivated this file: PR merged, branch reopened for the next slice.
# Before the fix, this case was declared stale and the live claim went with it.
case_ reused-branch              "closed"      3   live

case_ no-pr                      ""            0   live
case_ open-pr                    "open closed" 0   live
case_ closed-pr-nothing-to-merge "closed"      0   stale
case_ closed-pr-missing-branch   "closed"      ""  stale
case_ several-closed             "closed closed" 0 stale

# The structural false positive, reported by the Laravel session. Between the merge of one slice
# and the first commit of the next, a reused change branch meets all three conditions — closed
# PRs, nothing ahead of `main`, sometimes a deleted branch — while the work goes on. The change,
# for its part, knows it is not finished.
case_ change-in-progress         "closed"      0   live   change  left
case_ change-in-progress-no-branch "closed"    ""  live   change  left

# And the opposite must stay true, otherwise the fix would make the check useless: a change whose
# tasks are all checked has no claim left to hold.
case_ change-done                "closed"      0   stale  change  done

echo
if [ "$failures" -eq 0 ]; then
    echo "prises-check: 9 cases, all conforming"
else
    echo "prises-check: $failures failing case(s)"
fi
[ "$failures" -eq 0 ]
