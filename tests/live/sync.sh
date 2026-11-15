#!/bin/sh
# Copies the module into the live test Gibbon made by setup.sh. Usage: tests/live/sync.sh [work directory]
WORK="${1:-/tmp/servicedesk-live}"
MODULE="$(cd "$(dirname "$0")/../.." && pwd)"
mkdir -p "$WORK/core/modules/ServiceDesk"
rsync -a --delete --exclude .git --exclude .claude --exclude tests "$MODULE/" "$WORK/core/modules/ServiceDesk/"
echo "module copied to $WORK/core/modules/ServiceDesk"
