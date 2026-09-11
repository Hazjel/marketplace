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
    def test_prefixed_key_prepends_prefix(self):
        assert config._prefixed_key("chat:session:", prefix="blukios:") == "blukios:chat:session:"

    def test_empty_prefix_leaves_key_unprefixed(self):
        assert config._prefixed_key("chat:session:", prefix="") == "chat:session:"

    def test_not_idempotent_on_an_already_prefixed_input(self):
        # _prefixed_key is a plain prepend, not a general idempotent
        # operation -- calling it twice on the same string legitimately
        # doubles the prefix. That's fine because no call site does this;
        # the module-level SESSION_KEY etc. constants are each built from
        # a fixed, never-prefixed "chat:*" literal exactly once (see the
        # test below), not from re-prefixing an existing key.
        once  = config._prefixed_key("chat:session:", prefix="blukios:")
        twice = config._prefixed_key(once, prefix="blukios:")
        assert twice == "blukios:blukios:chat:session:"

    def test_all_chat_keys_fall_beneath_the_configured_prefix_exactly_once(self):
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


# ---------------------------------------------------------------------------
# _resolve_redis_config — component vars vs. the transitional legacy
# REDIS_URL fallback (Sprint C2.1B cutover window: the old --reload process
# picks up new source before its container env is actually recreated).
# ---------------------------------------------------------------------------
class TestResolveRedisConfig:
    def test_component_vars_are_authoritative_when_host_is_present(self):
        env = {
            "REDIS_HOST": "shared-redis",
            "REDIS_PORT": "6379",
            "REDIS_USERNAME": "blukios",
            "REDIS_PASSWORD": "s3cr3t",
            "REDIS_DB": "0",
            # Present but must be ignored -- REDIS_HOST already set.
            "REDIS_URL": "redis://old-redis:6379/5",
        }
        resolved = config._resolve_redis_config(env)
        assert resolved == {
            "host": "shared-redis",
            "port": 6379,
            "username": "blukios",
            "password": "s3cr3t",
            "db": 0,
        }

    def test_legacy_url_used_only_when_host_is_absent(self):
        env = {"REDIS_URL": "redis://redis:6379/0"}
        resolved = config._resolve_redis_config(env)
        assert resolved == {
            "host": "redis",
            "port": 6379,
            "username": None,
            "password": None,
            "db": 0,
        }

    def test_legacy_url_with_credentials_is_parsed(self):
        env = {"REDIS_URL": "redis://myuser:mypass@old-redis:6380/3"}
        resolved = config._resolve_redis_config(env)
        assert resolved == {
            "host": "old-redis",
            "port": 6380,
            "username": "myuser",
            "password": "mypass",
            "db": 3,
        }

    def test_neither_set_falls_back_to_localhost_defaults(self):
        resolved = config._resolve_redis_config({})
        assert resolved == {
            "host": "localhost",
            "port": 6379,
            "username": None,
            "password": None,
            "db": 0,
        }

    def test_explicit_port_overrides_the_legacy_urls_port(self):
        # A partial component override (just REDIS_PORT) while REDIS_HOST
        # is still absent -- unlikely in practice, but the component value
        # should still win over whatever the URL says.
        env = {"REDIS_URL": "redis://redis:6379/0", "REDIS_PORT": "7000"}
        resolved = config._resolve_redis_config(env)
        assert resolved["port"] == 7000
        assert resolved["host"] == "redis"  # still comes from the URL
