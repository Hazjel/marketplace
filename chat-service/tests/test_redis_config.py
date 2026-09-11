import config
from utils.redis_helper import redis_connection_kwargs, redis_connection_summary


# ---------------------------------------------------------------------------
# Key-prefix construction (Sprint C2.1B — shared Redis)
#
# Exercises the exact helper config.py builds SESSION_KEY/SUMMARY_KEY/
# LLM_CACHE_KEY/FEEDBACK_KEY from, rather than reloading the module with a
# monkeypatched env var — same behavior, without import/reload fragility.
# ---------------------------------------------------------------------------
class TestKeyPrefixing:
    def test_prefixed_key_applies_prefix_once(self):
        assert config._prefixed_key("chat:session:", prefix="blukios:") == "blukios:chat:session:"

    def test_empty_prefix_leaves_key_unprefixed(self):
        assert config._prefixed_key("chat:session:", prefix="") == "chat:session:"

    def test_no_double_prefix(self):
        key = config._prefixed_key("chat:session:", prefix="blukios:")
        assert key.count("blukios:") == 1

    def test_all_chat_keys_fall_beneath_the_configured_prefix(self):
        prefix = "blukios:"
        keys = {
            "session":  config._prefixed_key("chat:session:", prefix=prefix),
            "summary":  config._prefixed_key("chat:summary:", prefix=prefix),
            "llmcache": config._prefixed_key("chat:llmcache:", prefix=prefix),
            "feedback": config._prefixed_key("chat:feedback:", prefix=prefix),
        }
        for label, key in keys.items():
            assert key.startswith("blukios:"), f"{label} key missing prefix: {key}"
            assert key.count("blukios:") == 1, f"{label} key double-prefixed: {key}"
        assert keys["session"] == "blukios:chat:session:"
        assert keys["summary"] == "blukios:chat:summary:"
        assert keys["llmcache"] == "blukios:chat:llmcache:"
        assert keys["feedback"] == "blukios:chat:feedback:"


# ---------------------------------------------------------------------------
# Redis connection construction — must never require a credential-bearing
# URL, and a startup log line must never be able to leak the password.
# ---------------------------------------------------------------------------
class TestRedisConnectionBuilding:
    def test_kwargs_reflect_config(self, monkeypatch):
        monkeypatch.setattr(config, "REDIS_HOST", "shared-redis")
        monkeypatch.setattr(config, "REDIS_PORT", 6379)
        monkeypatch.setattr(config, "REDIS_USERNAME", "blukios")
        monkeypatch.setattr(config, "REDIS_PASSWORD", "s3cr3t")
        monkeypatch.setattr(config, "REDIS_DB", 0)

        kwargs = redis_connection_kwargs()

        assert kwargs["host"] == "shared-redis"
        assert kwargs["port"] == 6379
        assert kwargs["username"] == "blukios"
        assert kwargs["password"] == "s3cr3t"
        assert kwargs["db"] == 0
        assert kwargs["decode_responses"] is True

    def test_kwargs_allow_no_auth_for_a_bare_local_redis(self, monkeypatch):
        monkeypatch.setattr(config, "REDIS_HOST", "localhost")
        monkeypatch.setattr(config, "REDIS_USERNAME", None)
        monkeypatch.setattr(config, "REDIS_PASSWORD", None)

        kwargs = redis_connection_kwargs()

        assert kwargs["username"] is None
        assert kwargs["password"] is None

    def test_summary_never_contains_the_password(self, monkeypatch):
        monkeypatch.setattr(config, "REDIS_HOST", "shared-redis")
        monkeypatch.setattr(config, "REDIS_PORT", 6379)
        monkeypatch.setattr(config, "REDIS_USERNAME", "blukios")
        monkeypatch.setattr(config, "REDIS_PASSWORD", "s3cr3t-do-not-leak")
        monkeypatch.setattr(config, "REDIS_DB", 0)

        summary = redis_connection_summary()

        assert "s3cr3t-do-not-leak" not in summary
        assert "blukios" not in summary  # username excluded too, not just password
        assert summary == "shared-redis:6379/0"

    def test_summary_reflects_host_port_db(self, monkeypatch):
        monkeypatch.setattr(config, "REDIS_HOST", "127.0.0.1")
        monkeypatch.setattr(config, "REDIS_PORT", 6380)
        monkeypatch.setattr(config, "REDIS_DB", 2)

        assert redis_connection_summary() == "127.0.0.1:6380/2"
