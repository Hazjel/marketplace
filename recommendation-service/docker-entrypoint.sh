#!/bin/sh
set -e

# Container jalan sebagai root (gak ada USER directive) -- proses
# reload/write file di bind-mount ./recommendation-service:/app bikin
# ownership balik ke root di sisi host, host user jadi gak bisa git
# pull/edit file lagi (Permission denied) sampai di-chown manual.
# HOST_UID/HOST_GID di-passing dari docker-compose.yml (id -u/-g host user).
if [ -n "$HOST_UID" ] && [ -n "$HOST_GID" ]; then
    chown -R "$HOST_UID:$HOST_GID" /app
fi

echo "🚀 Starting Recommendation Service..."
exec python -m uvicorn main:app --host 0.0.0.0 --port 8002 --reload --reload-delay 1 --proxy-headers --forwarded-allow-ips='*'
