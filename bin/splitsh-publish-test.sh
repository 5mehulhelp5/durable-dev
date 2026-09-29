#!/usr/bin/env bash
#
# Check of `splitsh-publish.sh` on a throwaway repository, offline, with a stub splitsh-lite that
# "splits" by returning HEAD: in tag mode each sibling `self.version` requirement becomes a caret
# range on the tag, in one extra commit on top of the split, built without touching the working
# tree or the refs; branch mode keeps the split untouched.
set -uo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

failures=0
check() { # $1 description · $2 command that must succeed
    if eval "$2"; then echo "  ok        $1"; else echo "  FAIL      $1"; failures=$((failures + 1)); fi
}

export GIT_AUTHOR_NAME=t GIT_AUTHOR_EMAIL=t@t GIT_COMMITTER_NAME=t GIT_COMMITTER_EMAIL=t@t
unset SPLITSH_PUSH_TOKEN GITHUB_ACTIONS
g() { git -c init.defaultBranch=main "$@"; }

mkdir -p "$TMP/stub"
printf '#!/usr/bin/env bash\ngit rev-parse HEAD\n' > "$TMP/stub/splitsh-lite"
chmod +x "$TMP/stub/splitsh-lite"
export SPLITSH_LITE="$TMP/stub/splitsh-lite"

g init -q "$TMP/repo"
cd "$TMP/repo" || exit 1
mkdir bin && cp "$ROOT/bin/splitsh-publish.sh" bin/
cat > composer.json <<'JSON'
{
    "name": "gplanchat/durable-laravel",
    "description": "Laravel — ports",
    "require": {
        "gplanchat/durable": "self.version",
        "gplanchat/durable-bridge-illuminate": "self.version",
        "other/vendor": "self.version",
        "php": ">=8.2"
    },
    "require-dev": {
        "gplanchat/durable-bundle": "self.version"
    },
    "autoload": {
        "psr-4": {
            "Gplanchat\\Durable\\Laravel\\": "./"
        }
    }
}
JSON
echo readme > README.md
g add -A && g commit -q -m split && g tag v0.1.0-beta1
split="$(git rev-parse HEAD)"
refs_before="$(git show-ref)"

source bin/splitsh-publish.sh
set +e

out="$(rewrite_self_version "$split" v0.1.0-beta1)"
json="$(git show "$out:composer.json")"
check "a new commit is built on top of the split"      '[ "$out" != "$split" ] && [ "$(git rev-parse "$out^")" = "$split" ]'
check "gplanchat/durable becomes ^0.1.0-beta1"         '[ "$(jq -r ".require[\"gplanchat/durable\"]" <<<"$json")" = "^0.1.0-beta1" ]'
check "every sibling in require is rewritten"          '[ "$(jq -r ".require[\"gplanchat/durable-bridge-illuminate\"]" <<<"$json")" = "^0.1.0-beta1" ]'
check "a sibling in require-dev is rewritten"          '[ "$(jq -r ".[\"require-dev\"][\"gplanchat/durable-bundle\"]" <<<"$json")" = "^0.1.0-beta1" ]'
check "a non-sibling self.version is left alone"       '[ "$(jq -r ".require[\"other/vendor\"]" <<<"$json")" = "self.version" ]'
check "only the three sibling lines change"            '[ "$(git diff "$split" "$out" | grep -c "^[-+] ")" = 6 ]'
check "only composer.json changes"                     '[ "$(git diff --name-only "$split" "$out")" = composer.json ]'
check "the rewrite is deterministic"                   '[ "$(rewrite_self_version "$split" v0.1.0-beta1)" = "$out" ]'
check "the working tree is untouched"                  '[ -z "$(git status --porcelain)" ]'
check "the refs are untouched"                         '[ "$(git show-ref)" = "$refs_before" ]'

g checkout -q -b plain && echo '{"name": "x/y"}' > composer.json && g commit -q -am plain
plain="$(git rev-parse HEAD)"
check "no self.version, no extra commit"               '[ "$(rewrite_self_version "$plain" v0.1.0-beta1)" = "$plain" ]'
g checkout -q main

tag_out="$(bash bin/splitsh-publish.sh tag v0.1.0-beta1 2>&1)"
check "tag mode prints the rewritten SHA"              'grep -q "split SHA=$out for v0.1.0-beta1" <<<"$tag_out"'
g checkout -q main
branch_out="$(bash bin/splitsh-publish.sh 2>&1)"
check "branch mode keeps the split SHA"                'grep -q "split SHA=$split " <<<"$branch_out"'

[ "$failures" -eq 0 ] || { echo "$tag_out"; echo "$branch_out"; exit 1; }
