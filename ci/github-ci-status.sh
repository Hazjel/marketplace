#!/bin/sh
# Prints one verdict on stdout for the GitHub Actions CI run (.github/workflows/ci.yml)
# of $BUILD_COMMIT pushed to main; progress goes to stderr.
#
#   success      the run passed: the Jenkinsfile skips its own test stages
#   failure      the run failed: the Jenkinsfile stops before Deploy
#   unavailable  anything else: no run, cancelled, API error, rate limit;
#                the Jenkinsfile runs its local test stages as before
#
# Only curl is available in the Jenkins container (no jq), so the JSON is read
# with grep. With per_page=1 the response holds one run, whose "status" and
# "conclusion" keys each occur once. Anonymous API calls are limited to 60/h per
# IP and other tenants on this host share it, hence the 90 s poll interval;
# hitting the limit makes curl fail, which falls back to local tests.
set -u

wait_for_run=${GHA_WAIT_FOR_RUN:-600}       # a push run appears within seconds
wait_for_result=${GHA_WAIT_FOR_RESULT:-3600}
interval=${GHA_POLL_INTERVAL:-90}

if [ -z "${BUILD_COMMIT:-}" ]; then
    echo "BUILD_COMMIT is empty" >&2
    echo unavailable
    exit 0
fi

url="https://api.github.com/repos/Hazjel/marketplace/actions/workflows/ci.yml/runs?head_sha=$BUILD_COMMIT&event=push&per_page=1"
field() { printf '%s\n' "$body" | grep -o "\"$1\": \"[^\"]*\"" | head -n 1 | cut -d '"' -f 4; }

start=$(date +%s)
while :; do
    elapsed=$(( $(date +%s) - start ))
    if ! body=$(curl -fsS --max-time 20 -H 'Accept: application/vnd.github+json' "$url" 2>&1); then
        echo "GitHub API unavailable: $body" >&2
        echo unavailable
        exit 0
    fi
    status=$(field status)
    if [ -z "$status" ]; then
        if [ "$elapsed" -ge "$wait_for_run" ]; then
            echo "No GitHub CI run for $BUILD_COMMIT after ${elapsed}s" >&2
            echo unavailable
            exit 0
        fi
        echo "Waiting for a GitHub CI run for $BUILD_COMMIT (${elapsed}s)" >&2
    elif [ "$status" = completed ]; then
        conclusion=$(field conclusion)
        echo "GitHub CI $conclusion: $(field html_url)" >&2
        case "$conclusion" in
            success) echo success ;;
            failure) echo failure ;;
            *) echo unavailable ;;
        esac
        exit 0
    elif [ "$elapsed" -ge "$wait_for_result" ]; then
        echo "GitHub CI still $status after ${elapsed}s: $(field html_url)" >&2
        echo unavailable
        exit 0
    else
        echo "GitHub CI $status (${elapsed}s): $(field html_url)" >&2
    fi
    sleep "$interval"
done
