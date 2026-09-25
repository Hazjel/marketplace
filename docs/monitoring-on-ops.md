# Monitoring Blukios di stack ops

Blukios tidak menjalankan Prometheus dan Grafana sendiri. Server punya stack ops
(`/opt/ops-monitoring`), sama seperti Postgres, Mongo, dan Redis yang ada di
`/opt/shared-infra`. Blukios hanya mengeluarkan metrik; ops yang mengumpulkan dan
menampilkannya.

## Ke mana membukanya

| Layanan | Alamat | Cara masuk |
|---|---|---|
| Grafana ops | `https://ops.fthstack.my.id` | Cloudflare Access (login tim), lalu login Grafana |
| Prometheus ops | tidak punya hostname publik | SSH tunnel: `ssh -L 9090:127.0.0.1:10014 <user>@100.77.244.19`, lalu buka `http://localhost:9090` |

Alamat Grafana diambil dari `GF_SERVER_ROOT_URL` di `/opt/ops-monitoring/docker-compose.yml`.
Prometheus hanya dipublikasikan di `127.0.0.1:10014`, jadi tanpa tunnel ia hanya bisa
dijangkau dari server itu sendiri. Alternatifnya tanpa tunnel: di Grafana buka Explore,
pilih datasource Prometheus, dan jalankan query.

Login Grafana dipegang pemilik stack ops: compose ops mengisi password `admin` dari
`GRAFANA_ADMIN_PASSWORD` di `/opt/ops-monitoring/.env` (hanya bisa dibaca root). Minta
akun sendiri ke pemilik ops daripada memakai `admin`.

## Yang sudah dikerjakan di sisi Blukios

- Laravel mengeluarkan `/metrics`, chat-service dan recommendation-service masing-masing
  `/metrics`. Port yang dipublikasikan: nginx `8888`, chat-service `8001`,
  recommendation-service `8002`.
- Storage metrik Laravel ada di Redis bersama dengan prefix `<REDIS_PREFIX>prometheus:`.
  ACL Redis bersama hanya mengizinkan key di namespace aplikasi, dan library memakai
  `KEYS` untuk membaca Summary, yang ditolak; `App\Support\PrometheusRedisStorage`
  melewatinya karena aplikasi tidak memakai Summary.
- nginx menjawab 404 untuk `/metrics` dan `/ai/metrics` pada request yang membawa
  `CF-Connecting-IP`, yaitu semua trafik publik. Scraper di host tidak membawanya.

## Yang perlu dikerjakan pemilik root

Sisi ops tidak bisa ditulis oleh user deploy (`root:root`). Dari checkout repo ini di server:

```bash
sudo DRY_RUN=1 bash monitoring/ops/apply-on-ops.sh   # validasi dan tampilkan diff, tanpa mengubah
sudo bash monitoring/ops/apply-on-ops.sh
```

Skrip melakukan, berurutan:

1. Menambah tiga job (`blukios-laravel-api`, `blukios-chat-service`,
   `blukios-recommendation-service`, label `project="blukios"`) ke
   `prometheus/prometheus.yml`, setelah memvalidasinya dengan `promtool` versi ops.
   Cadangan disimpan sebagai `prometheus.yml.bak.<waktu>`.
2. Memuat ulang Prometheus lewat `/-/reload`. Kalau gagal, file dikembalikan dari cadangan.
3. Menyalin dua dashboard ke `grafana/dashboards/`. Grafana ops memuatnya sendiri dalam
   sekitar 30 detik.
4. Menunggu sampai ketiga target `up`.

Aman dijalankan ulang. Dashboard memakai datasource `uid: prometheus`, sama dengan yang
diprovisioning di Grafana ops, jadi tidak perlu diedit.

### Memeriksa hasilnya

- Grafana, Explore: `up{project="blukios"}` harus menghasilkan tiga seri bernilai 1.
- Dashboard **Laravel API Monitoring** dan **Chat Service Monitoring**.
- Prometheus, halaman `/targets`: tiga job `blukios-*` berstatus UP.

### Membatalkan

```bash
sudo cp /opt/ops-monitoring/prometheus/prometheus.yml.bak.<waktu> /opt/ops-monitoring/prometheus/prometheus.yml
sudo rm /opt/ops-monitoring/grafana/dashboards/blukios-*.json
curl -X POST http://127.0.0.1:10014/-/reload
```

## Yang sengaja tidak dipindah

- **Alerting.** `monitoring/grafana/provisioning/alerting/` tidak disalin. Notification
  policy Grafana bersifat tunggal, jadi menyalinnya ke Grafana ops akan menimpa policy
  milik project lain. Aturan alert Blukios baru boleh dipindah setelah pemilik ops
  menentukan contact point-nya.
- **Prometheus dan Grafana Blukios sendiri.** Sudah dikeluarkan dari `docker-compose.yml`.
  Untuk dev lokal keduanya ada di `docker-compose.local.yml`:
  `docker compose -f docker-compose.yml -f docker-compose.local.yml --profile monitoring up -d`
  (Prometheus `localhost:9090`, Grafana `localhost:3000`, login `admin`/`admin` hanya untuk laptop).

## Catatan

- Query dashboard tidak memfilter `job`. Kalau project lain kelak mengekspor metrik dengan
  nama yang sama (misalnya `http_requests_total`), panel bisa bercampur.
- Port `8001` dan `8002` dipublikasikan ke jaringan server (bukan lewat Cloudflare).
  Scraper memakainya; itu tidak berubah dari sebelumnya.
