#!/usr/bin/env bash
#
# Run the package's test suite against every supported Laravel major.
#
#   bash bin/test-matrix.sh                 # all supported versions
#   ATLAS_MATRIX_CELLS="10:8" bash bin/test-matrix.sh
#
# The package supports Laravel 10 through 13, and those four are genuinely
# different frameworks — a `casts()` method means nothing to Laravel 10, a
# Testbench 8 application boots differently from a Testbench 11 one. One cell
# per version, each a throwaway copy of the package with composer constrained to
# that framework, so the matrix never touches the working tree.
#
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
PHP="${PHP:-php}"
WORK="${ATLAS_MATRIX_DIR:-$(mktemp -d)}"

# Laravel major : testbench major (testbench 8 → Laravel 10, 9 → 11, 10 → 12, 11 → 13)
CELLS="${ATLAS_MATRIX_CELLS:-10:8 11:9 12:10 13:11}"

# The floor each major sets: a package cannot test Laravel 10 on PHP 8.4's
# syntax rules and Laravel 13 on PHP 8.1 at the same time, so a cell is skipped
# with a reason rather than failing.
declare -A PHP_FLOOR=([10]="8.1" [11]="8.2" [12]="8.2" [13]="8.3")

installed_php="$(php -r 'echo PHP_VERSION;')"
failures=0
summary=()

for cell in $CELLS; do
    laravel="${cell%%:*}"
    testbench="${cell##*:}"
    dir="$WORK/atlas-laravel-$laravel"

    floor="${PHP_FLOOR[$laravel]}"
    if $PHP -r "exit(version_compare(PHP_VERSION, '$floor', '>=') ? 0 : 1);"; then :; else
        summary+=("Laravel $laravel  skipped — needs PHP $floor, this shell has $installed_php")
        continue
    fi

    echo
    echo "── Laravel $laravel  (testbench $testbench, PHP $installed_php)"

    rm -rf "$dir"
    mkdir -p "$dir"
    rsync -a --exclude vendor --exclude node_modules --exclude composer.lock "$ROOT/" "$dir/"

    # --with narrows a cell without editing the package's own constraints.
    (cd "$dir" && composer update --no-interaction --no-progress --quiet \
        --with "laravel/framework:^$laravel.0" \
        --with "orchestra/testbench:^$testbench.0") || {
        summary+=("Laravel $laravel  FAILED — composer could not resolve")
        failures=$((failures + 1))
        continue
    }

    resolved="$(cd "$dir" && $PHP -r '
        $lock = json_decode(file_get_contents("composer.lock"), true);
        $want = ["laravel/framework", "orchestra/testbench", "phpunit/phpunit", "nikic/php-parser"];

        foreach ($lock["packages"] as $package) {
            if (in_array($package["name"], $want, true) || $package["name"] === "orchestra/testbench") {
                printf("%s %s  ", $package["name"], $package["version"]);
            }
        }

        foreach ($lock["packages-dev"] as $package) {
            if (in_array($package["name"], $want, true)) {
                printf("%s %s  ", $package["name"], $package["version"]);
            }
        }
    ' 2>/dev/null || echo 'unknown')"

    echo "   $resolved"

    output="$(cd "$dir" && composer test -- --colors=never 2>&1)" || true

    if grep -qE "^OK " <<<"$output"; then
        summary+=("Laravel $laravel  $(grep -E '^OK ' <<<"$output" | tail -1)")
    else
        echo "$output" | tail -n 18
        summary+=("Laravel $laravel  FAILED")
        failures=$((failures + 1))
    fi
done

echo
echo "──────── matrix ────────"
printf '  %s\n' "${summary[@]}"

exit $failures
