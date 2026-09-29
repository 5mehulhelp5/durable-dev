#!/usr/bin/env bash
#
# Check of the check. `check-root-lock.sh` runs against a throwaway fixture: one root, one path
# package, a lock written by hand. `composer` is replaced by a stub, which keeps the test offline;
# the stub fails when STUB_HASH_STALE is set, standing for a stale content-hash.
set -uo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

failures=0

# $1 case name · $2 the path package's require, as JSON · $3 expected: "pass" or "fail"
# $4 "hash-stale": the composer stub reports a stale content-hash
case_() {
    local name="$1" require="$2" expected="$3" hash="${4:-}"
    local box="$TMP/$name"
    mkdir -p "$box/bin" "$box/src/Pkg"
    cp "$ROOT/bin/check-root-lock.sh" "$box/bin/"

    printf '#!/bin/sh\n[ -z "$STUB_HASH_STALE" ]\n' > "$box/composer"
    chmod +x "$box/composer"

    printf '{"name": "acme/pkg", "require": %s, "require-dev": {"phpunit/phpunit": "^11"}}\n' \
        "$require" > "$box/src/Pkg/composer.json"
    cat > "$box/composer.lock" <<'LOCK'
{
    "packages": [
        {"name": "acme/pkg", "version": "dev-main",
         "dist": {"type": "path", "url": "src/Pkg"},
         "require": {"php": ">=8.2", "psr/log": "^3.0"},
         "require-dev": {"phpunit/phpunit": "^11"}},
        {"name": "psr/log", "version": "3.0.2",
         "dist": {"type": "zip", "url": "https://example.invalid/psr-log.zip"}}
    ],
    "packages-dev": []
}
LOCK

    local output code
    output=$(STUB_HASH_STALE="$hash" COMPOSER_BIN="$box/composer" bash "$box/bin/check-root-lock.sh" 2>&1)
    code=$?

    local got=pass
    [ "$code" -ne 0 ] && got=fail
    if [ "$got" = "$expected" ]; then
        echo "  ok   $name"
    else
        echo "  FAIL $name: expected $expected, got $got (exit $code)"
        sed 's/^/            /' <<<"$output"
        failures=$((failures + 1))
    fi
}

case_ fresh                  '{"php": ">=8.2", "psr/log": "^3.0"}'                 pass
case_ fresh-other-key-order  '{"psr/log": "^3.0", "php": ">=8.2"}'                 pass
case_ added-requirement      '{"php": ">=8.2", "psr/log": "^3.0", "psr/clock": "^1.0"}' fail
case_ removed-requirement    '{"php": ">=8.2"}'                                    fail
case_ moved-constraint       '{"php": ">=8.2", "psr/log": "^2.0 || ^3.0"}'         fail
case_ stale-content-hash     '{"php": ">=8.2", "psr/log": "^3.0"}'                 fail hash-stale

echo
if [ "$failures" -eq 0 ]; then
    echo "check-root-lock: 6 cases, all conforming"
else
    echo "check-root-lock: $failures failing case(s)"
fi
[ "$failures" -eq 0 ]
