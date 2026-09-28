#!/usr/bin/env bash
#
# Check of `clean-worktrees.sh` on a throwaway repository: a merged worktree goes, a merged one
# holding local work is refused and stays, an unmerged one and a detached one are not touched.
set -uo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

failures=0
check() { # $1 description · $2 command that must succeed
    if eval "$2"; then echo "  ok        $1"; else echo "  FAIL      $1"; failures=$((failures + 1)); fi
}

g() { git -c user.name=t -c user.email=t@t -c init.defaultBranch=main "$@"; }

g init -q --bare "$TMP/origin.git"
g clone -q "$TMP/origin.git" "$TMP/repo" 2>/dev/null
cd "$TMP/repo" || exit 1
g commit -q --allow-empty -m root
g push -q origin main

for b in merged dirty unmerged; do g branch "$b"; done
g checkout -q unmerged && g commit -q --allow-empty -m ahead && g checkout -q main
for b in merged dirty unmerged; do g worktree add -q "$TMP/wt-$b" "$b"; done
g worktree add -q --detach "$TMP/wt-detached" main
touch "$TMP/wt-dirty/untracked"

output="$(bash "$ROOT/bin/clean-worktrees.sh" 2>&1)"

check "merged worktree removed"             '[ ! -d "$TMP/wt-merged" ]'
check "merged worktree with work kept"      '[ -f "$TMP/wt-dirty/untracked" ]'
check "the refusal is reported"             'grep -q "REFUSED.*dirty" <<<"$output"'
check "unmerged worktree kept"              '[ -d "$TMP/wt-unmerged" ]'
check "detached worktree kept"              '[ -d "$TMP/wt-detached" ]'
check "main checkout kept"                  '[ -d "$TMP/repo/.git" ]'

[ "$failures" -eq 0 ] || { echo "$output"; exit 1; }
