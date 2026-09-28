#!/usr/bin/env bash
#
# Publish a version: tag a commit of origin/main, push the tag (Splitsh propagates it to the
# satellites, Packagist follows), then create the GitHub release (#350, WA008).
#
# Two traps this script exists for:
# - **A tag off origin/main.** The local `main` often carries a prise commit nobody pushed; a tag
#   on it publishes a commit that exists nowhere else, to ten satellites at once. Only a commit
#   reachable from origin/main is accepted.
# - **The wrong previous tag.** `gh release create --generate-notes` alone picks the previous tag
#   lexically, and `v0.1.0-alpha9` sorts after `v0.1.0-alpha10`: alpha10's notes listed alpha9's
#   PRs. The previous tag is chosen here in version order and passed as `--notes-start-tag`.
#
# Usage: bin/release.sh <tag> [<commit>] [--notes-file <file>] [--dry-run]
#   <tag>      vMAJOR.MINOR.PATCH, optionally -alphaN, -betaN or -rcN (a prerelease)
#   <commit>   defaults to the tip of origin/main
#   --notes-file   an editorial body, placed above the generated notes
#   --dry-run      say what would happen, change nothing
set -euo pipefail

tag=""
commit=""
notes_file=""
dry_run=0
while [ $# -gt 0 ]; do
    case "$1" in
        --dry-run) dry_run=1 ;;
        --notes-file) notes_file="${2:?--notes-file needs a file}"; shift ;;
        -*) echo "Unknown option $1" >&2; exit 2 ;;
        *) if [ -z "$tag" ]; then tag="$1"; elif [ -z "$commit" ]; then commit="$1"; else echo "Too many arguments" >&2; exit 2; fi ;;
    esac
    shift
done

if [[ ! "$tag" =~ ^v[0-9]+\.[0-9]+\.[0-9]+(-(alpha|beta|rc)[0-9]+)?$ ]]; then
    echo "The tag must read vMAJOR.MINOR.PATCH, optionally -alphaN, -betaN or -rcN: got '$tag'" >&2
    exit 2
fi

git fetch --quiet origin main --tags

sha="$(git rev-parse --verify "${commit:-origin/main}^{commit}")"
if ! git merge-base --is-ancestor "$sha" origin/main; then
    echo "Refused: $sha is not on origin/main. A version is only ever tagged from origin/main." >&2
    exit 1
fi

if git rev-parse --verify --quiet "refs/tags/$tag" >/dev/null || [ -n "$(git ls-remote origin "refs/tags/$tag")" ]; then
    echo "Refused: the tag $tag already exists." >&2
    exit 1
fi

# Version order, where a prerelease sorts before its release (versionsort.suffix).
previous="$(git -c versionsort.suffix=- tag -l 'v*' --sort=-v:refname | head -n1)"

prerelease=()
[[ "$tag" == *-* ]] && prerelease=(--prerelease)
notes=()
[ -n "$notes_file" ] && notes=(--notes-file "$notes_file")

echo "Tag      $tag on $sha (origin/main)"
echo "Previous ${previous:-none}"
if [ "$dry_run" -eq 1 ]; then
    echo "Dry run: nothing tagged, nothing released."
    exit 0
fi

git tag -a "$tag" -m "Version ${tag#v}" "$sha"
git push --quiet origin "refs/tags/$tag"
gh release create "$tag" --verify-tag --title "Version ${tag#v}" --generate-notes \
    ${previous:+--notes-start-tag "$previous"} "${prerelease[@]}" "${notes[@]}"
