import os
import shutil
import subprocess
import tempfile
import unittest
from pathlib import Path


REPO_ROOT = Path(__file__).resolve().parents[2]
JENKINSFILE = REPO_ROOT / "Jenkinsfile"
TEST_REVISION = "a" * 40
OLD_REVISION = "b" * 40
PREVIOUS_MARKER = "previous-deployed-revision\n"


def bash_command():
    return shutil.which("bash") or r"C:\Program Files\Git\bin\bash.exe"


def deploy_shell():
    text = JENKINSFILE.read_text(encoding="utf-8")
    deploy = text[text.index("stage('Deploy')") :]
    start = deploy.index("sh '''") + len("sh '''")
    end = deploy.index("'''", start)
    return deploy[start:end]


class DeployFixture:
    def __init__(
        self,
        *,
        frontend_changed="false",
        existing_images=None,
        health="200",
        buyer_body=TEST_REVISION,
        seller_body=TEST_REVISION,
        revision_mode="matching",
    ):
        self.tempdir = tempfile.TemporaryDirectory(prefix="seller-deploy-")
        self.root = Path(self.tempdir.name)
        self.project = self.root / "project"
        self.app_storage = self.project / "api-blue" / "storage" / "app"
        self.app_storage.mkdir(parents=True)
        self.marker = self.app_storage / "deployed-ok"
        self.marker.write_text(PREVIOUS_MARKER, encoding="utf-8")
        self.count_file = self.root / "revision-request-count"
        if existing_images is None:
            existing_images = (
                "api",
                "queue",
                "reverb",
                "scheduler",
                "frontend",
                "frontend-seller",
                "chat-service",
                "recommendation-service",
            )
        self.values = {
            "CURL_HEALTH": health,
            "CURL_BUYER": buyer_body,
            "CURL_SELLER": seller_body,
            "TARGET_REV": TEST_REVISION,
            "EXISTING_IMAGES": ",".join(existing_images),
            "REVISION_MODE": revision_mode,
            "REVISION_COUNT_FILE": str(self.count_file),
            "COMMAND_LOG": str(self.root / "commands.log"),
        }
        self.values["FRONTEND_CHANGED"] = frontend_changed

    def close(self):
        self.tempdir.cleanup()

    def run(self):
        env = os.environ.copy()
        env.update(self.values)
        env.update(
            {
                "HOST_PROJECT_DIR": str(self.project),
                "BUILD_COMMIT": TEST_REVISION,
                "FRONTEND_IMAGE_CHANGED": self.values["FRONTEND_CHANGED"],
                "PHP_IMAGE_CHANGED": "false",
                "CHAT_IMAGE_CHANGED": "false",
                "RECO_IMAGE_CHANGED": "false",
                "COMPOSER_CHANGED": "false",
                "BACKEND_CHANGED": "false",
                "CHAT_SERVICE_CHANGED": "false",
                "RECOMMENDATION_CHANGED": "false",
            }
        )
        helpers = r"""
logcmd() { printf '%s %s\n' "$1" "$*" >> "$COMMAND_LOG"; }
git() {
  logcmd git "$@"
  case " $* " in
    *" rev-parse "*) printf '%s\n' "$TARGET_REV" ;;
    *) return 0 ;;
  esac
}
docker() {
  logcmd docker "$@"
  if [ "$1" = image ] && [ "$2" = inspect ]; then
    image="$3"
    case ",$EXISTING_IMAGES," in
      *,${image#marketplace-},*) return 0 ;;
      *) return 1 ;;
    esac
  fi
  if [ "$1" = compose ] && [ "$2" = -p ] && [ "$4" = ps ]; then
    return 1
  fi
  return 0
}
curl() {
  logcmd curl "$@"
  url=""
  for arg in "$@"; do
    case "$arg" in
      http://*|https://*) url="$arg" ;;
    esac
  done
  case "$url" in
    *build-commit.txt*)
      count=0
      [ -f "$REVISION_COUNT_FILE" ] && count=$(cat "$REVISION_COUNT_FILE")
      count=$((count + 1))
      printf '%s\n' "$count" > "$REVISION_COUNT_FILE"
      case "$REVISION_MODE" in
        http22) return 22 ;;
        http22-body) printf '%s\n' "$CURL_BUYER"; return 22 ;;
        transient)
          if [ "$count" -le 2 ]; then body="$CURL_OLD"; else body="$CURL_CURRENT"; fi
          ;;
        *) body="" ;;
      esac
      if [ -z "$body" ]; then
        case "$url" in
          https://blukios.store/*) body="$CURL_BUYER" ;;
          https://seller.blukios.store/*) body="$CURL_SELLER" ;;
          *) return 22 ;;
        esac
      fi
      printf '%s\n' "$body"
      ;;
    *) printf '%s\n' "$CURL_HEALTH" ;;
  esac
}
sleep() { return 0; }
"""
        if self.values["REVISION_MODE"] == "transient":
            env.update({"CURL_OLD": OLD_REVISION, "CURL_CURRENT": TEST_REVISION})
        return subprocess.run(
            [bash_command(), "--noprofile", "--norc", "-e", "-s"],
            cwd=self.project,
            env=env,
            input=helpers + "\n" + deploy_shell() + "\n",
            capture_output=True,
            text=True,
        )

    def commands(self):
        path = Path(self.values["COMMAND_LOG"])
        return path.read_text(encoding="utf-8").splitlines() if path.exists() else []

    def revision_requests(self):
        return [line for line in self.commands() if "build-commit.txt" in line]

    def build_commands(self):
        return [line for line in self.commands() if "compose -p marketplace build" in line]

    def frontend_builds_both(self):
        return any("frontend frontend-seller" in line for line in self.build_commands())


class FrontendDeployRegressionTests(unittest.TestCase):
    def fixture(self, **kwargs):
        fixture = DeployFixture(**kwargs)
        self.addCleanup(fixture.close)
        return fixture

    def assert_success(self, fixture):
        result = fixture.run()
        self.assertEqual(result.returncode, 0, result.stdout + result.stderr)
        self.assertEqual(fixture.marker.read_text(encoding="utf-8"), TEST_REVISION + "\n")
        requests = fixture.revision_requests()
        self.assertTrue(any("https://blukios.store/" in line for line in requests), requests)
        self.assertTrue(any("https://seller.blukios.store/" in line for line in requests), requests)
        return result

    def assert_failed_without_advancing_marker(self, fixture):
        result = fixture.run()
        self.assertNotEqual(result.returncode, 0, result.stdout + result.stderr)
        self.assertEqual(fixture.marker.read_text(encoding="utf-8"), PREVIOUS_MARKER)
        self.assertIn("GAGAL: revisi frontend buyer/seller", result.stdout + result.stderr)

    def test_frontend_change_builds_buyer_and_seller_images(self):
        fixture = self.fixture(frontend_changed="true")
        self.assert_success(fixture)
        self.assertTrue(fixture.frontend_builds_both(), fixture.commands())

    def test_missing_buyer_image_rebuilds_both_images(self):
        fixture = self.fixture(existing_images=("api", "queue", "reverb", "scheduler", "frontend-seller", "chat-service", "recommendation-service"))
        self.assert_success(fixture)
        self.assertIn("frontend frontend-seller", "\n".join(fixture.build_commands()))

    def test_missing_seller_image_rebuilds_both_images(self):
        fixture = self.fixture(existing_images=("api", "queue", "reverb", "scheduler", "frontend", "chat-service", "recommendation-service"))
        self.assert_success(fixture)
        self.assertIn("frontend frontend-seller", "\n".join(fixture.build_commands()))

    def test_backend_only_deploy_reuses_cached_frontend_images(self):
        fixture = self.fixture(buyer_body=OLD_REVISION, seller_body=OLD_REVISION)
        self.assert_success(fixture)
        self.assertFalse(fixture.build_commands(), fixture.commands())

    def test_compose_up_explicitly_includes_both_frontend_services(self):
        fixture = self.fixture()
        self.assert_success(fixture)
        up = [line for line in fixture.commands() if " compose -p marketplace up -d" in line]
        self.assertTrue(up, fixture.commands())
        self.assertIn(" frontend frontend-seller ", f" {up[0]} ")

    def test_mismatched_served_frontend_revisions_fail_without_deployed_ok(self):
        fixture = self.fixture(seller_body=OLD_REVISION)
        self.assert_failed_without_advancing_marker(fixture)

    def test_frontend_rebuild_requires_revision_under_test(self):
        fixture = self.fixture(frontend_changed="true", buyer_body=OLD_REVISION, seller_body=OLD_REVISION)
        self.assert_failed_without_advancing_marker(fixture)

    def test_failed_frontend_revision_request_fails_without_deployed_ok(self):
        fixture = self.fixture(revision_mode="http22")
        self.assert_failed_without_advancing_marker(fixture)

    def test_failed_request_with_valid_sha_body_still_fails(self):
        fixture = self.fixture(revision_mode="http22-body")
        self.assert_failed_without_advancing_marker(fixture)

    def test_invalid_matching_html_body_fails(self):
        fixture = self.fixture(buyer_body="<html>error</html>", seller_body="<html>error</html>")
        self.assert_failed_without_advancing_marker(fixture)

    def test_invalid_matching_sha_newline_junk_fails(self):
        invalid = TEST_REVISION + "\njunk"
        fixture = self.fixture(buyer_body=invalid, seller_body=invalid)
        self.assert_failed_without_advancing_marker(fixture)

    def test_transient_old_pair_then_current_pair_succeeds(self):
        fixture = self.fixture(frontend_changed="true", revision_mode="transient")
        self.assert_success(fixture)
        self.assertGreaterEqual(int(fixture.count_file.read_text(encoding="utf-8")), 4)


if __name__ == "__main__":
    unittest.main()
