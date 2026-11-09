#!/usr/bin/env bash
set -euo pipefail

PASS_LABEL="${PASS_LABEL:-mbstring-enabled}"
REQUIRE_MBSTRING="${REQUIRE_MBSTRING:-1}"

export COMPOSER_ALLOW_SUPERUSER=1

fail_deprecation_output() {
    local label="$1"
    local file="$2"
    local stripped
    stripped="$(mktemp)"
    sed -E 's/\x1b\[[0-9;]*[[:alpha:]]//g' "${file}" >"${stripped}"
    if grep -E '(^|[[:space:]])(PHP[[:space:]]+)?(Warning|Deprecated|Notice):' "${stripped}" >/dev/null; then
        echo "${label}: output contains Warning, Deprecated, or Notice lines" >&2
        grep -E '(^|[[:space:]])(PHP[[:space:]]+)?(Warning|Deprecated|Notice):' "${stripped}" >&2 || true
        rm -f "${stripped}"
        exit 1
    fi
    rm -f "${stripped}"
}

if ! php -m | grep -qi '^mbstring$'; then
    HAS_MBSTRING=0
else
    HAS_MBSTRING=1
fi

if [[ "${REQUIRE_MBSTRING}" == "1" && "${HAS_MBSTRING}" != "1" ]]; then
    echo "Pass ${PASS_LABEL}: expected ext-mbstring to be loaded" >&2
    exit 1
fi

if [[ "${REQUIRE_MBSTRING}" == "0" && "${HAS_MBSTRING}" != "0" ]]; then
    echo "Pass ${PASS_LABEL}: ext-mbstring must not be loaded so the polyfill path is exercised" >&2
    exit 1
fi

php -v | tee "/tmp/php-version-${PASS_LABEL}.txt"

composer_out="$(mktemp)"
set +e
php /tmp/composer.phar update --no-interaction --prefer-dist --no-progress >"${composer_out}" 2>&1
composer_status=$?
set -e
cat "${composer_out}"
fail_deprecation_output "composer update (${PASS_LABEL})" "${composer_out}"
if [[ ${composer_status} -ne 0 ]]; then
    rm -f "${composer_out}"
    exit "${composer_status}"
fi
rm -f "${composer_out}"

pin_out="$(mktemp)"
set +e
php scripts/check-cluster-pins.php >"${pin_out}" 2>&1
pin_status=$?
set -e
cat "${pin_out}"
fail_deprecation_output "check-cluster-pins (${PASS_LABEL})" "${pin_out}"
if [[ ${pin_status} -ne 0 ]]; then
    rm -f "${pin_out}"
    exit "${pin_status}"
fi
if ! grep -Fq 'check-cluster-pins: wrong-SHA demo rejected bad reference as expected.' "${pin_out}"; then
    echo "check-cluster-pins: missing wrong-SHA demo success line (${PASS_LABEL})" >&2
    exit 1
fi
if ! grep -Fq 'check-cluster-pins: all cluster packages match expected SHAs and aliases.' "${pin_out}"; then
    echo "check-cluster-pins: missing final success line (${PASS_LABEL})" >&2
    exit 1
fi
rm -f "${pin_out}"

audit_out="$(mktemp)"
set +e
php /tmp/composer.phar audit >"${audit_out}" 2>&1
audit_status=$?
set -e
cat "${audit_out}"
fail_deprecation_output "composer audit (${PASS_LABEL})" "${audit_out}"
if [[ ${audit_status} -ne 0 ]]; then
    rm -f "${audit_out}"
    exit "${audit_status}"
fi
if ! grep -Fxq 'No security vulnerability advisories found.' "${audit_out}"; then
    echo "composer audit: missing exact success line (${PASS_LABEL})" >&2
    exit 1
fi
rm -f "${audit_out}"

devtools_out="$(mktemp)"
set +e
php /tmp/composer.phar update --no-interaction --prefer-dist --no-progress --working-dir=dev-tools >"${devtools_out}" 2>&1
devtools_status=$?
set -e
cat "${devtools_out}"
fail_deprecation_output "composer update dev-tools (${PASS_LABEL})" "${devtools_out}"
if [[ ${devtools_status} -ne 0 ]]; then
    rm -f "${devtools_out}"
    exit "${devtools_status}"
fi
rm -f "${devtools_out}"

phpunit_out="$(mktemp)"
set +e
if [[ "${REQUIRE_MBSTRING}" == "0" ]]; then
    php scripts/check-cluster-pins.php
    php -d max_execution_time=300 scripts/phpunit-polyfill.php --colors=never >"${phpunit_out}" 2>&1
else
    make run-phpunit ARGS="--colors=never" >"${phpunit_out}" 2>&1
fi
phpunit_status=$?
set -e
cat "${phpunit_out}"
fail_deprecation_output "phpunit (${PASS_LABEL})" "${phpunit_out}"
if [[ ${phpunit_status} -ne 0 ]]; then
    if [[ "${REQUIRE_MBSTRING}" == "0" ]]; then
        echo "Pass ${PASS_LABEL}: PHPUnit failed with polyfill path (no ext-mbstring)." >&2
        echo "Treat symfony/polyfill-mbstring or pdfparser native-mbstring assumptions per pipeline rules." >&2
    fi
    rm -f "${phpunit_out}"
    exit "${phpunit_status}"
fi
if ! grep -E '^OK \([0-9]+ tests?, [0-9]+ assertions\)$' "${phpunit_out}" >/dev/null; then
    echo "phpunit: missing strict OK success line (${PASS_LABEL})" >&2
    exit 1
fi
OK_LINE="$(grep -E '^OK \([0-9]+ tests?, [0-9]+ assertions\)$' "${phpunit_out}" | tail -n 1)"
echo "Pass ${PASS_LABEL} strict success line: ${OK_LINE}"
rm -f "${phpunit_out}"

echo "Pass ${PASS_LABEL}: all steps passed."
