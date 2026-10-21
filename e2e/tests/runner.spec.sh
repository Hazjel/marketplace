#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/../.."
mkdir -p e2e/artifacts
work=$(mktemp -d "$PWD/e2e/artifacts/runner-check.XXXXXX")
mkdir -p "$work/e2e/artifacts"
cp e2e/run.sh "$work/e2e/run.sh"
trap 'rm -f "$work/docker" "$work/commands.log" "$work/e2e/run.sh" "$work/e2e/artifacts/stack.log"; rmdir "$work/e2e/artifacts" "$work/e2e" "$work"' EXIT
export COMMAND_LOG="$work/commands.log"
cat >"$work/docker" <<'MOCK'
#!/usr/bin/env bash
echo "$*" >>"$COMMAND_LOG"
case " $* " in
    *' ps '*) if [ "$ACTIVE" = 1 ]; then echo existing-e2e; fi ;;
    *' build '*) exit "$BUILD_EXIT" ;;
    *' run '*) exit "$TEST_EXIT" ;;
esac
MOCK
chmod +x "$work/docker"
export PATH="$work:$PATH"

for scenario in all-ok all-test-fail all-build-fail up-ok test-fail test-build-fail down overlap; do
    export BUILD_EXIT=0 TEST_EXIT=0 ACTIVE=0
    mode=all expected=0 expected_down=1
    case "$scenario" in
        all-test-fail) TEST_EXIT=23; expected=23 ;;
        all-build-fail) BUILD_EXIT=17; expected=17 ;;
        up-ok) mode=up; expected_down=0 ;;
        test-fail) mode='test'; TEST_EXIT=23; expected=23; expected_down=0 ;;
        test-build-fail) mode='test'; BUILD_EXIT=17; expected=17; expected_down=0 ;;
        down) mode=down ;;
        overlap) ACTIVE=1; expected=1; expected_down=0 ;;
    esac
    : >"$COMMAND_LOG"
    result=0
    bash "$work/e2e/run.sh" "$mode" >/dev/null 2>&1 || result=$?
    down_count=$(grep -c ' down ' "$COMMAND_LOG" || true)
    if [ "$result" -ne "$expected" ] || [ "$down_count" -ne "$expected_down" ]; then
        echo "FAIL $scenario: exit=$result cleanup=$down_count" >&2
        cat "$COMMAND_LOG" >&2
        exit 1
    fi
    echo "PASS $scenario"
done
