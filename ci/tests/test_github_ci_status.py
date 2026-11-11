import os
import re
import shutil
import subprocess
import tempfile
import unittest
from pathlib import Path


REPO_ROOT = Path(__file__).resolve().parents[2]
SCRIPT = REPO_ROOT / "ci" / "github-ci-status.sh"
TOKEN = "test-token-value-123"


def sh_command():
    return shutil.which("sh") or shutil.which("bash") or r"C:\Program Files\Git\bin\bash.exe"


def run_body(status, conclusion="null"):
    if conclusion != "null":
        conclusion = f'"{conclusion}"'
    return (
        '{\n  "total_count": 1,\n  "workflow_runs": [\n    {\n'
        '      "id": 1,\n'
        f'      "status": "{status}",\n'
        f'      "conclusion": {conclusion},\n'
        '      "html_url": "https://github.com/Hazjel/marketplace/actions/runs/1"\n'
        "    }\n  ]\n}\n"
    )


NO_RUN = '{\n  "total_count": 0,\n  "workflow_runs": [\n\n  ]\n}\n'
# curl -f exit codes: 22 for an HTTP error (403/429 rate limit), 28 for --max-time.
RATE_LIMITED = (22, "curl: (22) The requested URL returned error: 403\n")
TIMED_OUT = (28, "curl: (28) Operation timed out after 20001 milliseconds\n")

# curl answers from the response list (the last one repeats). The fake clock only
# moves by time actually waited: sleep by its argument, a timed-out curl by its
# --max-time. Answered requests take no time.
STUBS = r"""
clock_add() { echo $(( $(cat "$STUB_DIR/clock") + $1 )) > "$STUB_DIR/clock"; }
curl() {
  n=$(( $(cat "$STUB_DIR/count") + 1 ))
  echo "$n" > "$STUB_DIR/count"
  printf '%s\n' "$*" >> "$STUB_DIR/argv"
  case " $* " in *" -K - "*) cat >> "$STUB_DIR/config" ;; esac
  [ "$n" -le "$STUB_LAST" ] || n=$STUB_LAST
  rc=$(cat "$STUB_DIR/$n.rc")
  if [ "$rc" = 28 ]; then
    waited=""
    prev=""
    for arg in "$@"; do
      [ "$prev" = --max-time ] && waited=$arg
      prev=$arg
    done
    [ -n "$waited" ] || waited=300   # no --max-time: curl's own connect timeout
    clock_add "$waited"
  fi
  cat "$STUB_DIR/$n.body"
  return "$rc"
}
date() { cat "$STUB_DIR/clock"; }
sleep() { echo "$1" >> "$STUB_DIR/sleeps"; clock_add "$1"; }
"""


class GithubCiStatusTests(unittest.TestCase):
    def run_script(self, responses, token=None, wait="3600", commit="a" * 40):
        """Runs the script with the default poll interval and returns what it did."""
        tmp = tempfile.TemporaryDirectory(prefix="gha-status-")
        self.addCleanup(tmp.cleanup)
        stub = Path(tmp.name)
        for i, response in enumerate(responses, start=1):
            rc, body = response if isinstance(response, tuple) else (0, response)
            (stub / f"{i}.body").write_text(body, encoding="utf-8", newline="\n")
            (stub / f"{i}.rc").write_text(str(rc), encoding="utf-8", newline="\n")
        (stub / "count").write_text("0", encoding="utf-8", newline="\n")
        (stub / "clock").write_text("1000", encoding="utf-8", newline="\n")
        script = stub / "run.sh"
        script.write_text(STUBS + SCRIPT.read_text(encoding="utf-8"), encoding="utf-8", newline="\n")

        env = {k: v for k, v in os.environ.items() if k not in ("GHA_STATUS_TOKEN", "GHA_POLL_INTERVAL")}
        env.update(
            {
                "STUB_DIR": stub.as_posix(),
                "STUB_LAST": str(len(responses)),
                "BUILD_COMMIT": commit,
                "GHA_WAIT_FOR_RESULT": wait,
            }
        )
        if token is not None:
            env["GHA_STATUS_TOKEN"] = token
        # A loop that never waits would spin forever on the fake clock.
        result = subprocess.run(
            [sh_command(), script.as_posix()], env=env, capture_output=True, text=True, timeout=60
        )
        self.assertEqual(result.returncode, 0, result.stdout + result.stderr)
        self.assertNotIn(TOKEN, result.stdout + result.stderr)
        read = lambda name: (stub / name).read_text(encoding="utf-8") if (stub / name).exists() else ""
        argv = read("argv")
        return {
            "verdict": result.stdout.strip(),
            "calls": int(read("count")),
            "max_times": [int(m) for m in re.findall(r"--max-time (\d+)", argv)],
            "sleeps": [int(s) for s in read("sleeps").split()],
            "waited": int(read("clock")) - 1000,
            "argv": argv,
            "config": read("config"),
            "stderr": result.stderr,
        }

    def assertRun(self, run, verdict, max_times, sleeps):
        self.assertEqual(run["verdict"], verdict, run["stderr"])
        self.assertEqual(run["calls"], len(max_times), run["argv"])
        self.assertEqual(run["max_times"], max_times, run["argv"])
        self.assertEqual(run["sleeps"], sleeps, run["stderr"])

    def test_success(self):
        self.assertRun(self.run_script([run_body("completed", "success")]), "success", [20], [])

    def test_failure(self):
        self.assertRun(self.run_script([run_body("completed", "failure")]), "failure", [20], [])

    def test_no_run_then_pending_then_success_polls_every_90_s(self):
        run = self.run_script(
            [NO_RUN, run_body("queued"), run_body("in_progress"), run_body("completed", "success")]
        )
        self.assertRun(run, "success", [20, 20, 20, 20], [90, 90, 90])

    def test_rate_limited_then_success_keeps_polling(self):
        run = self.run_script([RATE_LIMITED, RATE_LIMITED, run_body("completed", "success")])
        self.assertRun(run, "success", [20, 20, 20], [90, 90])
        self.assertIn("returned error: 403", run["stderr"])

    def test_timeout_then_failure_keeps_polling(self):
        run = self.run_script([TIMED_OUT, run_body("completed", "failure")])
        self.assertRun(run, "failure", [20, 20], [90])
        self.assertEqual(run["waited"], 20 + 90)

    def test_still_pending_after_the_bound_is_unavailable(self):
        # Polls at 0, 90 and 180 s, sleeps only the 70 s left, polls once more at 250 s.
        run = self.run_script([run_body("in_progress")], wait="250")
        self.assertRun(run, "unavailable", [20, 20, 20, 1], [90, 90, 70])
        self.assertEqual(run["waited"], 250)
        self.assertIn("No GitHub CI result", run["stderr"])

    def test_rate_limited_until_the_bound_is_unavailable(self):
        run = self.run_script([RATE_LIMITED], wait="250")
        self.assertRun(run, "unavailable", [20, 20, 20, 1], [90, 90, 70])
        self.assertEqual(run["waited"], 250)

    def test_timeouts_never_overrun_the_bound(self):
        # 0-20 s timeout, sleep to 110 s, then only the 15 s left for the request.
        run = self.run_script([TIMED_OUT], wait="125")
        self.assertRun(run, "unavailable", [20, 15], [90])
        self.assertEqual(run["waited"], 125)

    def test_cancelled_run_is_unavailable_without_waiting(self):
        run = self.run_script([run_body("completed", "cancelled")])
        self.assertRun(run, "unavailable", [20], [])

    def test_empty_commit_is_unavailable(self):
        run = self.run_script([run_body("completed", "success")], commit="")
        self.assertRun(run, "unavailable", [], [])

    def test_without_token_sends_no_authorization(self):
        run = self.run_script([run_body("completed", "success")])
        self.assertEqual(run["verdict"], "success")
        self.assertNotIn("-K", run["argv"])
        self.assertNotIn("Authorization", run["argv"] + run["config"])
        self.assertIn("anonymously", run["stderr"])

    def test_with_token_sends_it_only_through_stdin_config(self):
        run = self.run_script([RATE_LIMITED, run_body("completed", "success")], token=TOKEN)
        self.assertRun(run, "success", [20, 20], [90])
        self.assertIn(" -K - ", f" {run['argv'].splitlines()[0]} ")
        self.assertNotIn(TOKEN, run["argv"])
        self.assertEqual(run["config"].count(f'header = "Authorization: Bearer {TOKEN}"'), 2)
        self.assertIn("github-ci-status-token", run["stderr"])


if __name__ == "__main__":
    unittest.main()
