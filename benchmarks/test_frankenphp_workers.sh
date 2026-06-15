#!/bin/sh
# Test FrankenPHP worker mode with different num values: 1, 2, 3, 4
# Uses parallel clients equal to worker count for fair comparison.

set -e

URL="http://frankenphp/bench"
DURATION="5"
CADDYFILE="/Users/liquan/Documents/web/skim_framework/Caddyfile"
DC_FILES="-f /Users/liquan/Documents/web/skim_framework/docker-compose.yml -f /Users/liquan/Documents/web/skim_framework/docker-compose.frankenphp.yml"

echo "========================================"
echo "FrankenPHP Worker Scaling Test"
echo "Parallel clients = workers"
echo "========================================"
echo ""

for NUM in 1 2 3 4; do
    echo "--- Testing num=${NUM} (clients=${NUM}) ---"

    # Update Caddyfile worker count
    sed -i.bak "s/num [0-9]*/num ${NUM}/" "$CADDYFILE"

    # Rebuild and restart
    docker compose ${DC_FILES} build frankenphp >/dev/null 2>&1
    docker compose ${DC_FILES} up -d --force-recreate frankenphp >/dev/null 2>&1

    # Wait for startup
    sleep 3

    # Run parallel benchmark from app container
    docker compose ${DC_FILES} exec -T app php benchmarks/test_frankenphp_parallel.php "${URL}" "${NUM}" "${DURATION}"
    echo ""
done

# Restore Caddyfile
mv "${CADDYFILE}.bak" "$CADDYFILE"

echo "========================================"
echo "Done. Caddyfile restored to original."
echo "========================================"
