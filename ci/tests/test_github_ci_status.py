import os
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

# curl answers from the response list (the last one repeats); date advances 100 s per call.
STUBS = r"""
curl() {
  n=$(( $(cat "$STUB_DIR/count") + 1 ))
  echo "$n" > "$STUB_DIR/count"
  printf '%s\n' "$*" >> "$STUB_DIR/argv"
  case " $* " in *" -K - "*) cat >> "$STUB_DIR/config" ;; esac
  [ "$n" -le "$STUB_LAST" ] || n=$STUB_LAST
  cat "$STUB_DIR/$n.body"
  return "$(cat "$STUB_DIR/$n.rc")"
}
date() {
  t=$(( $(cat "$STUB_DIR/clock") + 100 ))
  echo "$t" > "$STUB_DIR/clock"
  echo "$t"
}
sleep() { :; }
"""


class GithubCiStatusTests(unittest.TestCase):
    def run_script(self, responses, token=None, wait="3600", commit="a" * 40):
        tmp = tempfile.TemporaryDirectory(prefix="gha-status-")
        self.addCleanup(tmp.cleanup)
        stub = Path(tmp.name)
        for i, response in enumerate(responses, start=1):
            rc, body = response if isinstance(response, tuple) else (0, response)
            (stub / f"{i}.body").write_text(body, encoding="utf-8", newline="\n")
            (stub / f"{i}.rc").write_text(str(rc), encoding="utf-8", newline="\n")
        (stub / "count").write_text("0", encoding="utf-8", newline="\n")
        (stub / "clock").write_text("0", encoding="utf-8", newline="\n")
        script = stub / "run.sh"
        script.write_text(STUBS + SCRIPT.read_text(encoding="utf-8"), encoding="utf-8", newline="\n")

        env = {k: v for k, v in os.environ.items() if k != "GHA_STATUS_TOKEN"}
        env.update(
            {
                "STUB_DIR": stub.as_posix(),
                "STUB_LAST": str(len(responses)),
                "BUILD_COMMIT": commit,
                "GHA_WAIT_FOR_RESULT": wait,
                "GHA_POLL_INTERVAL": "0",
            }
        )
        if token is not None:
            env["GHA_STATUS_TOKEN"] = token
        result = subprocess.run(
            [sh_command(), script.as_posix()], env=env, capture_output=True, text=True
        )
        self.assertEqual(result.returncode, 0, result.stdout + result.stderr)
        self.assertNotIn(TOKEN, result.stdout + result.stderr)
        read = lambda name: (stub / name).read_text(encoding="utf-8") if (stub / name).exists() else ""
        return result.stdout.strip(), int(read("count")), read("argv"), read("config"), result.stderr

    def test_success(self):
        verdict, calls, *_ = self.run_script([run_body("completed", "success")])
        self.assertEqual((verdict, calls), ("success", 1))

    def test_failure(self):
        verdict, calls, *_ = self.run_script([run_body("completed", "failure")])
        self.assertEqual((verdict, calls), ("failure", 1))

    def test_no_run_then_pending_then_success_polls_until_known(self):
        verdict, calls, *_ = self.run_script(
            [NO_RUN, run_body("queued"), run_body("in_progress"), run_body("completed", "success")]
        )
        self.assertEqual((verdict, calls), ("success", 4))

    def test_rate_limited_then_success_keeps_polling(self):
        verdict, calls, _, _, stderr = self.run_script([RATE_LIMITED, RATE_LIMITED, run_body("completed", "success")])
        self.assertEqual((verdict, calls), ("success", 3))
        self.assertIn("returned error: 403", stderr)

    def test_timeout_then_failure_keeps_polling(self):
        verdict, calls, *_ = self.run_script([TIMED_OUT, run_body("completed", "failure")])
        self.assertEqual((verdict, calls), ("failure", 2))

    def test_still_pending_after_the_bound_is_unavailable(self):
        # Clock reads 100, 200, ...: polls at 100 s, 200 s and 300 s >= 250 s, then gives up.
        verdict, calls, _, _, stderr = self.run_script([run_body("in_progress")], wait="250")
        self.assertEqual((verdict, calls), ("unavailable", 3))
        self.assertIn("No GitHub CI result", stderr)

    def test_rate_limited_until_the_bound_is_unavailable(self):
        verdict, calls, *_ = self.run_script([RATE_LIMITED], wait="250")
        self.assertEqual((verdict, calls), ("unavailable", 3))

    def test_timeout_until_the_bound_is_unavailable(self):
        verdict, calls, *_ = self.run_script([TIMED_OUT], wait="250")
        self.assertEqual((verdict, calls), ("unavailable", 3))

    def test_cancelled_run_is_unavailable_without_waiting(self):
        verdict, calls, *_ = self.run_script([run_body("completed", "cancelled")])
        self.assertEqual((verdict, calls), ("unavailable", 1))

    def test_empty_commit_is_unavailable(self):
        verdict, calls, *_ = self.run_script([run_body("completed", "success")], commit="")
        self.assertEqual((verdict, calls), ("unavailable", 0))

    def test_without_token_sends_no_authorization(self):
        verdict, _, argv, config, stderr = self.run_script([run_body("completed", "success")])
        self.assertEqual(verdict, "success")
        self.assertNotIn("-K", argv)
        self.assertNotIn("Authorization", argv + config)
        self.assertIn("anonymously", stderr)

    def test_with_token_sends_it_only_through_stdin_config(self):
        verdict, calls, argv, config, stderr = self.run_script(
            [RATE_LIMITED, run_body("completed", "success")], token=TOKEN
        )
        self.assertEqual((verdict, calls), ("success", 2))
        self.assertIn(" -K - ", f" {argv.splitlines()[0]} ")
        self.assertNotIn(TOKEN, argv)
        self.assertEqual(config.count(f'header = "Authorization: Bearer {TOKEN}"'), 2)
        self.assertIn("github-ci-status-token", stderr)


if __name__ == "__main__":
    unittest.main()
