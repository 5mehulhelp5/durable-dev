#!/usr/bin/env bash
# The root composer.lock must match every manifest it installs (#640). Two checks, no resolve:
#   1. `composer validate --check-lock`: the root composer.json against the lock's content-hash.
#   2. Each path package the lock records (dist.type "path"): its require, require-dev, conflict,
#      provide and replace, as its composer.json says them now, against the lock's entry for it.
#      The content-hash covers only the root manifest, so a path package that gains a requirement
#      leaves check 1 green while `composer update --lock` would change the lock.
# A failure names the package and prints the diff. Fix: `composer update --lock <package>`, which
# is a supervised change (CLAUDE.md). COMPOSER_BIN overrides the composer command.
# ponytail: a stale transitive entry (a third-party package whose own requirements moved) is not
# seen; only a full `composer update --lock --dry-run` sees that, at the cost of the network.
set -euo pipefail
cd "$(dirname "$0")/.."

COMPOSER_BIN=${COMPOSER_BIN:-composer}
status=0

$COMPOSER_BIN validate --check-lock --no-check-all --no-check-publish --quiet composer.json || {
    echo "composer.lock: content-hash does not match the root composer.json" >&2
    status=1
}

fields='{require: (.require // {}), "require-dev": (.["require-dev"] // {}),
         conflict: (.conflict // {}), provide: (.provide // {}), replace: (.replace // {})}'

while IFS=$'\t' read -r name dir; do
    if [ ! -f "$dir/composer.json" ]; then
        echo "$name: the lock points at $dir, which has no composer.json" >&2
        status=1
        continue
    fi
    if ! out=$(diff -u --label "composer.lock ($name)" --label "$dir/composer.json" \
            <(jq -S --arg n "$name" '(.packages + ."packages-dev")[] | select(.name == $n) | '"$fields" composer.lock) \
            <(jq -S "$fields" "$dir/composer.json")); then
        echo "$name: the lock is stale against $dir/composer.json" >&2
        echo "$out" >&2
        status=1
    fi
done < <(jq -r '(.packages + ."packages-dev")[] | select(.dist.type == "path") | [.name, .dist.url] | @tsv' composer.lock)

[ "$status" -eq 0 ] && echo "composer.lock: consistent with the root and path manifests"
exit $status
