#!/bin/sh
# Prints one verdict on stdout for the GitHub Actions CI run (.github/workflows/ci.yml)
# of $BUILD_COMMIT pushed to main; progress goes to stderr.
#
#   success      the run passed: the Jenkinsfile skips its own test stages and deploys
#   failure      the run failed: the Jenkinsfile stops before Deploy
#   unavailable  no usable result within GHA_WAIT_FOR_RESULT (no run yet, API
#                error, rate limit, still running) or the run ended another way
#                (cancelled, skipped); the Jenkinsfile stops without deploying
#                and without running its local test stages
#
# While the result is not known yet (no run, curl failed, run not completed)
# the API is polled again every GHA_POLL_INTERVAL seconds until
# GHA_WAIT_FOR_RESULT seconds have passed.
#
# Only curl is available in the Jenkins container (no jq), so the JSON is read
# with grep. With per_page=1 the response holds one run, whose "status" and
# "conclusion" keys each occur once. Anonymous API calls are limited to 60/h per
# IP and other tenants on this host share it, hence the 90 s poll interval.
# GHA_STATUS_TOKEN (optional, a read-only token from the Jenkins credential
# github-ci-status-token) lifts that limit; it reaches curl through a config on
# stdin, so it never appears in a process list or in this script's output.
set -u

wait_for_result=${GHA_WAIT_FOR_RESULT:-3600}
interval=${GHA_POLL_INTERVAL:-90}

if [ -z "${BUILD_COMMIT:-}" ]; then
    echo "BUILD_COMMIT is empty" >&2
    echo unavailable
    exit 0
fi

url="https://api.github.com/repos/Hazjel/marketplace/actions/workflows/ci.yml/runs?head_sha=$BUILD_COMMIT&event=push&per_page=1"
field() { printf '%s\n' "$body" | grep -o "\"$1\": \"[^\"]*\"" | head -n 1 | cut -d '"' -f 4; }
fetch() {
    if [ -n "${GHA_STATUS_TOKEN:-}" ]; then
        printf 'header = "Authorization: Bearer %s"\n' "$GHA_STATUS_TOKEN" |
            curl -K - -fsS --max-time 20 -H 'Accept: application/vnd.github+json' "$url" 2>&1
    else
        curl -fsS --max-time 20 -H 'Accept: application/vnd.github+json' "$url" 2>&1
    fi
}

if [ -n "${GHA_STATUS_TOKEN:-}" ]; then
    echo "Reading GitHub CI with the github-ci-status-token credential" >&2
else
    echo "Reading GitHub CI anonymously (60 requests/h shared by this IP)" >&2
fi

start=$(date +%s)
while :; do
    elapsed=$(( $(date +%s) - start ))
    if ! body=$(fetch); then
        echo "GitHub API unavailable (${elapsed}s): $body" >&2
    else
        status=$(field status)
        if [ -z "$status" ]; then
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
        else
            echo "GitHub CI $status (${elapsed}s): $(field html_url)" >&2
        fi
    fi
    if [ "$elapsed" -ge "$wait_for_result" ]; then
        echo "No GitHub CI result for $BUILD_COMMIT after ${elapsed}s" >&2
        echo unavailable
        exit 0
    fi
    sleep "$interval"
done
