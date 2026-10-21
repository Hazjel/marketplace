#!/usr/bin/env bash
set -Eeuo pipefail
cd "$(dirname "$0")/.."

compose() {
    MSYS_NO_PATHCONV=1 docker compose --project-name blukios-e2e --project-directory . \
        --env-file e2e/compose.env -f e2e/docker-compose.e2e.yml "$@"
}

started=$SECONDS
mode=${1:-all}
if [ "$#" -gt 0 ]; then shift; fi
case "$mode" in
    all|up|test|down) ;;
    *) echo 'Usage: bash e2e/run.sh [up|test|down] [Playwright arguments]' >&2; exit 2 ;;
esac

if [ "$mode" = down ]; then
    compose down --volumes --remove-orphans --timeout 10
    exit
fi
if [ "$mode" = test ]; then
    compose exec -T web wget -q -O /dev/null http://127.0.0.1/
    compose build playwright
    compose run --rm --no-deps playwright "$@"
    exit
fi

# A second startup must not tear down a stack being used by another tester.
if [ -n "$(docker ps -aq --filter label=com.docker.compose.project=blukios-e2e)" ]; then
    echo 'blukios-e2e already has containers; use test or down first.' >&2
    exit 1
fi

cleanup() {
    result=$?
    trap - EXIT INT TERM
    if [ "$result" -ne 0 ]; then
        mkdir -p e2e/artifacts
        compose logs --no-color >e2e/artifacts/stack.log 2>&1 || true
    fi
    compose down --volumes --remove-orphans --timeout 10 || result=1
    echo "blukios-e2e finished in $((SECONDS - started))s (exit $result)"
    exit "$result"
}
trap cleanup EXIT
trap 'exit 130' INT
trap 'exit 143' TERM

compose build api web seller playwright
compose up --wait --wait-timeout 180 api web seller
port=18080
echo "Buyer: http://127.0.0.1:$port"
echo 'Seller: http://127.0.0.1:18081'
echo "API: http://127.0.0.1:$port/api/health"
if [ "$mode" = up ]; then
    trap - EXIT INT TERM
else
    compose run --rm --no-deps playwright "$@"
fi
