#!/usr/bin/env bash
#
# Removes every worktree whose branch is already in origin/main (#374). Never `--force`: a worktree
# holding uncommitted or untracked work is refused by git, reported, and left for its owner. The
# main checkout and detached worktrees are not judged. Branches are kept; `git branch -d` is a
# separate step.
set -uo pipefail

root="$(cd "$(git rev-parse --path-format=absolute --git-common-dir)/.." && pwd)"
cd "$root" || exit 1
git fetch -q origin || echo "fetch failed, judging against the local origin/main" >&2

removed=0 refused=0
path='' branch=''
judge() {
    if [ -n "$path" ] && [ "$path" != "$root" ] && [ -n "$branch" ] \
        && git merge-base --is-ancestor "$branch" origin/main; then
        if err="$(git worktree remove "$path" 2>&1)"; then
            echo "removed   $branch  $path"; removed=$((removed + 1))
        else
            echo "REFUSED   $branch  $path — ${err//$'\n'/ }"; refused=$((refused + 1))
        fi
    fi
    path='' branch=''
}

while IFS= read -r line; do
    case $line in
        'worktree '*) path="${line#worktree }" ;;
        'branch refs/heads/'*) branch="${line#branch refs/heads/}" ;;
        '') judge ;;
    esac
done < <(git worktree list --porcelain; echo)

echo "worktrees: $removed removed, $refused refused"
