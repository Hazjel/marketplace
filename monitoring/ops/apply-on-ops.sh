#!/usr/bin/env bash
# Menyambungkan Blukios ke stack ops: menambah job scrape ke Prometheus ops dan
# menyalin dua dashboard ke Grafana ops. Dijalankan pemilik root di server:
#   sudo bash monitoring/ops/apply-on-ops.sh
#   sudo DRY_RUN=1 bash monitoring/ops/apply-on-ops.sh    # hanya validasi dan tampilkan diff
# Aman dijalankan ulang: job yang sudah ada tidak ditambah lagi.
set -euo pipefail

OPS_DIR=${OPS_DIR:-/opt/ops-monitoring}
RELOAD_URL=${RELOAD_URL:-http://127.0.0.1:10014/-/reload}
QUERY_URL=${QUERY_URL:-http://127.0.0.1:10014/api/v1/query}
PROM_IMAGE=${PROM_IMAGE:-prom/prometheus:v2.54.0}   # versi yang sama dengan compose ops

HERE=$(cd "$(dirname "$0")" && pwd)
REPO=$(cd "$HERE/../.." && pwd)
CONF="$OPS_DIR/prometheus/prometheus.yml"
DASH_DIR="$OPS_DIR/grafana/dashboards"
DRY_RUN=${DRY_RUN:-0}

[ -f "$CONF" ] || { echo "tidak ada $CONF" >&2; exit 1; }

tmp=$(mktemp)
trap 'rm -f "$tmp"' EXIT

if grep -q "job_name: 'blukios-laravel-api'" "$CONF"; then
  echo "job Blukios sudah ada di $CONF, bagian Prometheus dilewati"
else
  { cat "$CONF"; [ -n "$(tail -c1 "$CONF")" ] && echo; echo; cat "$HERE/blukios-scrape.yml"; } > "$tmp"
  chmod 644 "$tmp"   # mktemp membuat 0600, container promtool berjalan sebagai user lain

  docker run --rm --entrypoint promtool -v "$tmp:/etc/prometheus/prometheus.yml:ro" \
    "$PROM_IMAGE" check config /etc/prometheus/prometheus.yml
  diff -u "$CONF" "$tmp" || true

  if [ "$DRY_RUN" = 1 ]; then
    echo "DRY_RUN: tidak ada yang diubah"
  else
    backup="$CONF.bak.$(date +%Y%m%d-%H%M%S)"
    cp -a "$CONF" "$backup"
    cat "$tmp" > "$CONF"
    if ! curl -fsS -X POST "$RELOAD_URL"; then
      echo "reload gagal, mengembalikan $backup" >&2
      cat "$backup" > "$CONF"
      exit 1
    fi
    echo "Prometheus dimuat ulang, cadangan: $backup"
  fi
fi

for f in laravel-api/laravel-api.json chat-service/chat-service.json; do
  dest="$DASH_DIR/blukios-$(basename "$f")"
  if [ "$DRY_RUN" = 1 ]; then
    echo "DRY_RUN: akan menyalin $f ke $dest"
  else
    install -m 644 "$REPO/monitoring/grafana/dashboards/$f" "$dest"
    echo "dashboard: $dest"
  fi
done

[ "$DRY_RUN" = 1 ] && exit 0

echo "menunggu target Blukios..."
up=0
for _ in $(seq 1 12); do
  up=$(curl -fsS -G "$QUERY_URL" --data-urlencode 'query=count(up{project="blukios"} == 1)' |
    python3 -c 'import json,sys; r=json.load(sys.stdin)["data"]["result"]; print(r[0]["value"][1] if r else 0)')
  if [ "$up" = 3 ]; then
    echo "3 dari 3 target Blukios up"
    exit 0
  fi
  sleep 5
done
echo "hanya $up dari 3 target up; cek halaman /targets di Prometheus" >&2
exit 1
