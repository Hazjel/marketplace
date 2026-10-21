#!/bin/sh
set +e
npm test -- "$@"
result=$?
rm -rf /work/artifacts/report /work/artifacts/test-results
if [ "$result" -ne 0 ]; then
    for output in report test-results; do
        if [ -d "/tmp/blukios-e2e/$output" ]; then
            cp -a "/tmp/blukios-e2e/$output" /work/artifacts/ || echo "Could not copy $output artifacts" >&2
        fi
    done
fi
exit "$result"
