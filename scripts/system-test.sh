#!/usr/bin/env bash
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
IMAGE_TAG_MB="pdfparser-system-test:mbstring"
IMAGE_TAG_NO_MB="pdfparser-system-test:no-mbstring"
USE_SUDO_DOCKER="${USE_SUDO_DOCKER:-0}"

docker_cmd() {
    if [[ "${USE_SUDO_DOCKER}" == "1" ]]; then
        sudo docker "$@"
    else
        docker "$@"
    fi
}

build_image() {
    local target="$1"
    local tag="$2"
    docker_cmd build \
        --target "${target}" \
        -f "${REPO_ROOT}/docker/system-test/Dockerfile" \
        -t "${tag}" \
        "${REPO_ROOT}"
}

run_pass() {
    local image="$1"
    local label="$2"
    local require_mbstring="$3"
    docker_cmd run --rm \
        -v "${REPO_ROOT}:/app" \
        -w /app \
        -e PASS_LABEL="${label}" \
        -e REQUIRE_MBSTRING="${require_mbstring}" \
        "${image}" \
        bash -lc '
            set -euo pipefail
            git config --global --add safe.directory /app
            COMPOSER_URL="https://getcomposer.org/download/2.8.10/composer.phar"
            COMPOSER_SHA256="28dbb6bd8bef31479c7985b774c130a8bda37dbe63c35b56f6cb6bc377427573"
            curl -fsSL -o /tmp/composer.phar "${COMPOSER_URL}"
            echo "${COMPOSER_SHA256} /tmp/composer.phar" | sha256sum -c -
            bash /app/scripts/system-test-inner.sh
        '
}

echo "Building images (digest-pinned php:8.1-cli)..."
build_image php-with-mbstring "${IMAGE_TAG_MB}"
build_image php-without-mbstring "${IMAGE_TAG_NO_MB}"

echo "=== Pass 1: ext-mbstring enabled (native mbstring) ==="
run_pass "${IMAGE_TAG_MB}" "mbstring-enabled" "1"

echo "=== Pass 2: no ext-mbstring (polyfill mbstring path) ==="
run_pass "${IMAGE_TAG_NO_MB}" "polyfill-only" "0"

echo "system-test: both passes passed."
