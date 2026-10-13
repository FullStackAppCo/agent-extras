#!/usr/bin/env bash
#
# Shared helpers for the git hooks. Source from a hook:
#
#   source "$(dirname "${BASH_SOURCE[0]}")/lib.sh"
#
# Prefer Herd so the site's isolated PHP version is used, then Sail on machines
# with Docker but no host PHP (its stack must be up), falling back to the
# binaries on the PATH.

ROOT="$(git rev-parse --show-toplevel)"

if command -v herd >/dev/null 2>&1; then
    composer() { herd composer "$@"; }
    artisan() { herd php artisan "$@"; }
elif [ -x "$ROOT/vendor/bin/sail" ] && ! command -v php >/dev/null 2>&1; then
    composer() { (cd "$ROOT" && vendor/bin/sail composer "$@"); }
    artisan() { (cd "$ROOT" && vendor/bin/sail artisan "$@"); }
else
    composer() { command composer "$@"; }
    artisan() { php artisan "$@"; }
fi
