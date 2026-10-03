# Image for the chat-service and recommendation-service stages, built with the
# service directory as context. Tag = hash of this file + that service's
# requirements.txt, so the slow install (chromadb, scikit-surprise compiles C)
# runs once per dependency change instead of on every build (#124 sat 26+ min
# in pip install on this host's disk).
# FROM must match the services' production Dockerfiles.
FROM python:3.11-slim

COPY requirements.txt /tmp/requirements.txt
RUN apt-get update -qq \
    && apt-get install -y -qq --no-install-recommends build-essential >/dev/null \
    && pip install --quiet --no-cache-dir -r /tmp/requirements.txt ruff pytest \
    && apt-get purge -y -qq --auto-remove build-essential >/dev/null \
    && rm -rf /var/lib/apt/lists/*

# pip-audit in its own venv audits the service's installed packages
# (--path below), not a fresh resolve of requirements.txt, which would
# reinstall everything on every build.
RUN python -m venv /opt/pip-audit \
    && /opt/pip-audit/bin/pip install --quiet --no-cache-dir pip-audit

# pip and setuptools ship with the base image as build tooling, not service
# dependencies; upgraded so pip-audit reports on what the services run. Kept
# as the last layer so changing it reuses the cached install above.
RUN pip install --quiet --no-cache-dir --upgrade pip setuptools
