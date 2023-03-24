#!/bin/bash
# Prepare Playwright container before testing
# Usage : ./test-init.sh

YELLOW='\033[33m'
RESET='\033[0m'
if ! ddev --version >/dev/null 2>&1; then
    printf "%bDdev is required for this script. Please see docs/ddev.md.%b\n" "${YELLOW}" "${RESET}"
    exit 1
fi

ddev exec -s playwright yarn --cwd ./var/www/html/my-code/crowdsec-bouncer-lib/tests/end-to-end --force && \
ddev exec -s playwright yarn global add cross-env
