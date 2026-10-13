#!/usr/bin/env bash
#
# Shared helpers for the git hooks. Source from a hook:
#
#   source "$(dirname "${BASH_SOURCE[0]}")/lib.sh"
#
# Prefer Herd so the site's isolated PHP version is used, then Sail on machines
# with Docker but no host PHP (its stack must be up), falling back to the
# binaries on the PATH.
#
# In an application the commands run through `artisan`. In a package under
# development there is no `artisan`, so they run through the Testbench CLI
# instead, which boots the package's service provider from testbench.yaml.

ROOT="$(git rev-parse --show-toplevel)"

if [ -f "$ROOT/artisan" ]; then
    ARTISAN="artisan"
elif [ -f "$ROOT/vendor/bin/testbench" ]; then
    ARTISAN="vendor/bin/testbench"
else
    ARTISAN=""
fi

if command -v herd >/dev/null 2>&1; then
    composer() { herd composer "$@"; }
    php_run() { (cd "$ROOT" && herd php "$@"); }
elif [ -x "$ROOT/vendor/bin/sail" ] && ! command -v php >/dev/null 2>&1; then
    composer() { (cd "$ROOT" && vendor/bin/sail composer "$@"); }
    php_run() { (cd "$ROOT" && vendor/bin/sail php "$@"); }
else
    composer() { command composer "$@"; }
    php_run() { (cd "$ROOT" && php "$@"); }
fi

artisan() {
    if [ -z "$ARTISAN" ]; then
        echo "Unable to find artisan or vendor/bin/testbench in $ROOT." >&2
        return 1
    fi

    php_run "$ARTISAN" "$@"
}
