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
# Laravel Zero has neither, so they run through the binary its composer.json
# lists under `bin` (`application` unless the app was renamed).

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

# Print the first binary composer.json lists under `bin`, if it exists.
composer_bin() {
    local bin
    bin="$(php_run -r '$bin = json_decode((string) @file_get_contents("composer.json"), true)["bin"] ?? []; echo is_array($bin) ? ($bin[0] ?? "") : $bin;')"

    if [ -n "$bin" ] && [ -f "$ROOT/$bin" ]; then
        echo "$bin"
    fi
}

artisan() {
    if [ -z "$ARTISAN" ]; then
        ARTISAN="$(composer_bin)"
    fi

    if [ -z "$ARTISAN" ]; then
        echo "Unable to find artisan, vendor/bin/testbench or a composer.json bin in $ROOT." >&2
        return 1
    fi

    php_run "$ARTISAN" "$@"
}
